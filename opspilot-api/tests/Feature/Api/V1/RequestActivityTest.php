<?php

namespace Tests\Feature\Api\V1;

use App\Actions\RejectRequestApproval;
use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestActivityType;
use App\Enums\RequestApprovalStatus;
use App\Enums\RequestFieldType;
use App\Enums\RequestStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestActivity;
use App\Models\RequestApproval;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\RequestActivityRecorder;
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RequestActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_sequential_flow_records_complete_ordered_audit_history(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->definition();
        $firstStep = $this->roleStep($workflow, 1, 'Manager');
        $secondStep = $this->roleStep($workflow, 2, 'Finance');
        $submission = $this->createDraft($workspace, $type, $requester);

        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $approvals = $submission->approvals()->get();
        $this->authenticate($owner);
        $this->postJson($this->approveUrl($workspace, $approvals[0]))->assertOk();
        $this->postJson($this->approveUrl($workspace, $approvals[1]))->assertOk();

        $activities = $submission->activities()->orderBy('id')->get();
        $this->assertSame([
            RequestActivityType::RequestCreated,
            RequestActivityType::RequestSubmitted,
            RequestActivityType::ApprovalActivated,
            RequestActivityType::ApprovalApproved,
            RequestActivityType::ApprovalActivated,
            RequestActivityType::ApprovalApproved,
            RequestActivityType::RequestApproved,
        ], $activities->pluck('type')->all());
        $this->assertSame(
            [$requester->id, $requester->id, null, $owner->id, null, $owner->id, $owner->id],
            $activities->pluck('actor_id')->all(),
        );
        $this->assertSame($type->id, $activities[0]->metadata['request_type_id']);
        $this->assertSame($workflow->id, $activities[1]->metadata['workflow_id']);
        $this->assertSame($firstStep->id, $activities[2]->metadata['workflow_step_id']);
        $this->assertSame($approvals[0]->id, $activities[3]->request_approval_id);
        $this->assertSame($secondStep->id, $activities[4]->metadata['workflow_step_id']);
        $this->assertSame(RequestStatus::Approved, $submission->fresh()->status);
    }

    public function test_rejection_and_cancellation_record_request_level_outcomes_without_noisy_step_cancellations(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->definition();
        $this->roleStep($workflow, 1);
        $rejected = $this->createDraft($workspace, $type, $requester);
        $this->postJson($this->submitUrl($workspace, $rejected))->assertOk();
        $this->authenticate($owner);
        $this->postJson($this->rejectUrl($workspace, $rejected->approvals()->firstOrFail()))->assertOk();
        $this->assertSame([
            'request_created', 'request_submitted', 'approval_activated', 'approval_rejected', 'request_rejected',
        ], $rejected->activities()->orderBy('id')->pluck('type')->map->value->all());

        $cancelled = $this->createDraft($workspace, $type, $requester);
        $this->postJson($this->submitUrl($workspace, $cancelled))->assertOk();
        $this->postJson($this->cancelUrl($workspace, $cancelled))->assertOk();
        $this->assertSame([
            'request_created', 'request_submitted', 'approval_activated', 'request_cancelled',
        ], $cancelled->activities()->orderBy('id')->pluck('type')->map->value->all());
        $this->assertSame(0, RequestActivity::query()->where('type', 'approval_cancelled')->count());
    }

    public function test_all_skipped_auto_approval_and_failed_submission_have_correct_transactional_activity(): void
    {
        [, $workspace, $requester, $type, $workflow] = $this->definition();
        $amount = RequestTypeField::factory()->create([
            'request_type_id' => $type,
            'key' => 'amount',
            'type' => RequestFieldType::Number,
        ]);
        $step = $this->roleStep($workflow, 1);
        $step->conditions()->create([
            'request_type_field_id' => $amount->id,
            'operator' => 'greater_than',
            'value' => 100,
            'position' => 1,
        ]);
        $approved = $this->createDraft($workspace, $type, $requester, ['amount' => 10]);
        $this->postJson($this->submitUrl($workspace, $approved))->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame(
            ['request_created', 'request_submitted', 'request_approved'],
            $approved->activities()->orderBy('id')->pluck('type')->map->value->all(),
        );
        $this->assertNull($approved->activities()->where('type', 'request_approved')->firstOrFail()->actor_id);

        [$invalidOwner, $invalidWorkspace, $invalidRequester, $invalidType] = $this->definition(createWorkflow: false);
        Workflow::factory()->create([
            'workspace_id' => $invalidWorkspace,
            'request_type_id' => $invalidType,
            'created_by' => $invalidOwner,
            'status' => WorkflowStatus::Active,
            'draft_guard' => null,
            'active_guard' => 1,
            'published_at' => now(),
        ]);
        $failed = $this->createDraft($invalidWorkspace, $invalidType, $invalidRequester);
        Notification::fake();
        $this->postJson($this->submitUrl($invalidWorkspace, $failed))->assertUnprocessable();
        $this->assertSame(['request_created'], $failed->activities()->pluck('type')->map->value->all());
        $this->assertSame(RequestStatus::Draft, $failed->fresh()->status);
        $this->assertSame(0, $failed->approvals()->count());
        Notification::assertNothingSent();
    }

    public function test_failed_rejection_rolls_back_state_and_all_rejection_activity(): void
    {
        Notification::fake();
        [$owner, $workspace, $requester, $type, $workflow] = $this->definition();
        $this->roleStep($workflow, 1);
        $submission = $this->createDraft($workspace, $type, $requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $approval = $submission->approvals()->firstOrFail();
        $before = $submission->activities()->count();
        Notification::fake();
        $this->mock(RequestActivityRecorder::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit write failed'));
        });

        try {
            app(RejectRequestApproval::class)->handle($approval, $owner);
            $this->fail('Expected rejection to roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit write failed', $exception->getMessage());
        }

        $this->assertSame(RequestApprovalStatus::Pending, $approval->fresh()->status);
        $this->assertSame(RequestStatus::Submitted, $submission->fresh()->status);
        $this->assertSame($before, $submission->activities()->count());
        $this->assertSame(0, $submission->activities()->whereIn('type', [
            RequestActivityType::ApprovalRejected,
            RequestActivityType::RequestRejected,
        ])->count());
        Notification::assertNothingSent();
    }

    public function test_timeline_is_newest_first_paginated_rich_private_and_view_authorized(): void
    {
        Storage::fake('local');
        [, $workspace, $requester, $type, $workflow] = $this->definition();
        $this->roleStep($workflow, 1);
        $submission = $this->createDraft($workspace, $type, $requester);
        $this->postJson($this->commentsUrl($workspace, $submission), ['body' => 'Operational note'])->assertCreated();
        $this->post($this->attachmentsUrl($workspace, $submission), [
            'file' => UploadedFile::fake()->create('quote.pdf', 2, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->getJson($this->activityUrl($workspace, $submission).'?per_page=2')->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.type', 'attachment_uploaded')
            ->assertJsonPath('data.0.attachment.original_name', 'quote.pdf')
            ->assertJsonMissingPath('data.0.attachment.disk')
            ->assertJsonMissingPath('data.0.attachment.path')
            ->assertJsonPath('data.1.type', 'comment_added')
            ->assertJsonPath('data.1.comment.body', 'Operational note');

        $auditor = User::factory()->create();
        $unrelated = User::factory()->create();
        $this->member($workspace, $auditor, WorkspaceRole::Auditor);
        $this->member($workspace, $unrelated, WorkspaceRole::Approver);
        $this->authenticate($auditor);
        $this->getJson($this->activityUrl($workspace, $submission))->assertOk();
        $this->authenticate($unrelated);
        $this->getJson($this->activityUrl($workspace, $submission))->assertForbidden();
    }

    /** @return array{User, Workspace, User, RequestType, ?Workflow} */
    private function definition(bool $createWorkflow = true): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner]);
        $requester = User::factory()->create();
        $this->member($workspace, $owner, WorkspaceRole::Owner);
        $this->member($workspace, $requester, WorkspaceRole::Requester);
        $type = RequestType::factory()->create([
            'workspace_id' => $workspace,
            'created_by' => $owner,
            'is_active' => true,
        ]);
        $workflow = $createWorkflow ? Workflow::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $type,
            'created_by' => $owner,
            'status' => WorkflowStatus::Active,
            'draft_guard' => null,
            'active_guard' => 1,
            'published_at' => now(),
        ]) : null;

        return [$owner, $workspace, $requester, $type, $workflow];
    }

    private function member(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        app(SynchronizeWorkspacePermissions::class)->handle($workspace);
        WorkspaceMembership::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            ['joined_at' => now()],
        );
        app(WorkspacePermissions::class)->assign($user, $workspace, $role);
    }

    private function roleStep(Workflow $workflow, int $position, string $name = 'Approval'): WorkflowStep
    {
        return WorkflowStep::factory()->create([
            'workflow_id' => $workflow,
            'position' => $position,
            'name' => $name,
            'approver_type' => 'role',
            'approver_role' => WorkspaceRole::Owner,
            'approver_user_id' => null,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function createDraft(
        Workspace $workspace,
        RequestType $type,
        User $requester,
        array $payload = [],
    ): RequestSubmission {
        $this->authenticate($requester);
        $response = $this->postJson(
            "/api/v1/workspaces/{$workspace->id}/request-types/{$type->id}/requests",
            ['payload' => $payload],
        )->assertCreated();

        return RequestSubmission::query()->findOrFail($response->json('data.id'));
    }

    private function authenticate(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    private function requestUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return "/api/v1/workspaces/{$workspace->id}/requests/{$submission->id}";
    }

    private function submitUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return $this->requestUrl($workspace, $submission).'/submit';
    }

    private function cancelUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return $this->requestUrl($workspace, $submission).'/cancel';
    }

    private function commentsUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return $this->requestUrl($workspace, $submission).'/comments';
    }

    private function attachmentsUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return $this->requestUrl($workspace, $submission).'/attachments';
    }

    private function activityUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return $this->requestUrl($workspace, $submission).'/activity';
    }

    private function approvalUrl(Workspace $workspace, RequestApproval $approval): string
    {
        return "/api/v1/workspaces/{$workspace->id}/approvals/{$approval->id}";
    }

    private function approveUrl(Workspace $workspace, RequestApproval $approval): string
    {
        return $this->approvalUrl($workspace, $approval).'/approve';
    }

    private function rejectUrl(Workspace $workspace, RequestApproval $approval): string
    {
        return $this->approvalUrl($workspace, $approval).'/reject';
    }
}

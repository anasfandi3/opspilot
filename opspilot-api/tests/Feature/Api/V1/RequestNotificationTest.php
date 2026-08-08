<?php

namespace Tests\Feature\Api\V1;

use App\Actions\CancelRequest;
use App\Actions\CreateRequestComment;
use App\Actions\StoreRequestAttachment;
use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestApprovalStatus;
use App\Enums\RequestFieldType;
use App\Enums\RequestStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestApproval;
use App\Models\RequestApprovalAssignee;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Notifications\ApprovalAssignedNotification;
use App\Notifications\RequestApprovedNotification;
use App\Notifications\RequestAttachmentUploadedNotification;
use App\Notifications\RequestCancelledNotification;
use App\Notifications\RequestCommentAddedNotification;
use App\Notifications\RequestRejectedNotification;
use App\Support\WorkspacePermissions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesDisposableSqliteDatabase;
use Tests\TestCase;

class RequestNotificationTest extends TestCase
{
    use UsesDisposableSqliteDatabase;

    public function test_initial_role_approval_notifies_each_current_assignee_once_with_stable_safe_payload(): void
    {
        Notification::fake();
        [$owner, $workspace, $requester, $type, $workflow] = $this->definition();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $unrelated = User::factory()->create();
        $this->member($workspace, $first, WorkspaceRole::Approver);
        $this->member($workspace, $second, WorkspaceRole::Approver);
        $this->member($workspace, $unrelated, WorkspaceRole::Requester);
        $this->roleStep($workflow, 1, WorkspaceRole::Approver, 'Finance approval');
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);

        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();

        foreach ([$first, $second] as $recipient) {
            Notification::assertSentTo($recipient, ApprovalAssignedNotification::class,
                function (ApprovalAssignedNotification $notification, array $channels) use ($recipient, $workspace, $submission): bool {
                    $payload = $notification->toDatabase($recipient);
                    $this->assertSame(['database', 'mail'], $channels);
                    $this->assertSame('approval_assigned', $payload['event']);
                    $this->assertSame($workspace->id, $payload['workspace']['id']);
                    $this->assertSame($submission->id, $payload['request']['id']);
                    $this->assertSame('Finance approval', $payload['approval']['workflow_step_name']);
                    $this->assertArrayNotHasKey('payload', $payload['request']);
                    $this->assertArrayNotHasKey('definition_snapshot', $payload['request']);
                    $this->assertTrue($notification->afterCommit);

                    return true;
                });
        }
        Notification::assertNotSentTo([$owner, $requester, $unrelated], ApprovalAssignedNotification::class);
        Notification::assertCount(2);
    }

    public function test_next_step_filters_removed_assignee_and_all_skipped_notifies_requester_only(): void
    {
        Notification::fake();
        [$owner, $workspace, $requester, $type, $workflow] = $this->definition();
        $removed = User::factory()->create();
        $this->member($workspace, $removed, WorkspaceRole::Approver);
        $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $this->userStep($workflow, 2, $removed);
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        app(WorkspacePermissions::class)->remove($removed, $workspace);
        WorkspaceMembership::query()->whereBelongsTo($workspace)->whereBelongsTo($removed)->delete();
        Notification::fake();
        $this->authenticate($owner);

        $this->postJson($this->approveUrl($workspace, $submission->approvals()->firstOrFail()))->assertOk();
        Notification::assertNothingSent();

        Notification::fake();
        [, $autoWorkspace, $autoRequester, $autoType, $autoWorkflow] = $this->definition();
        $amount = RequestTypeField::factory()->create([
            'request_type_id' => $autoType,
            'key' => 'amount',
            'type' => RequestFieldType::Number,
        ]);
        $skipped = $this->roleStep($autoWorkflow, 1, WorkspaceRole::Owner);
        $skipped->conditions()->create([
            'request_type_field_id' => $amount->id,
            'operator' => 'greater_than',
            'value' => 100,
            'position' => 1,
        ]);
        $auto = $this->submission($autoWorkspace, $autoType, $autoRequester, ['amount' => 10]);
        $this->authenticate($autoRequester);
        $this->postJson($this->submitUrl($autoWorkspace, $auto))->assertOk()->assertJsonPath('data.status', 'approved');
        Notification::assertSentTo($autoRequester, RequestApprovedNotification::class);
        Notification::assertNotSentTo($autoRequester, ApprovalAssignedNotification::class);
        Notification::assertCount(1);
    }

    public function test_approval_progression_and_final_outcome_do_not_duplicate_on_stale_retries(): void
    {
        Notification::fake();
        [$owner, $workspace, $requester, $type, $workflow] = $this->definition();
        $this->roleStep($workflow, 1, WorkspaceRole::Owner, 'Manager');
        $this->roleStep($workflow, 2, WorkspaceRole::Owner, 'Director');
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $approvals = $submission->approvals()->get();
        Notification::fake();
        $this->authenticate($owner);

        $this->postJson($this->approveUrl($workspace, $approvals[0]))->assertOk();
        Notification::assertSentTo($owner, ApprovalAssignedNotification::class);
        Notification::assertCount(1);
        Notification::fake();
        $this->postJson($this->approveUrl($workspace, $approvals[1]))->assertOk();
        $this->postJson($this->approveUrl($workspace, $approvals[1]))->assertForbidden();
        $this->postJson($this->rejectUrl($workspace, $approvals[1]))->assertForbidden();
        Notification::assertSentTo($requester, RequestApprovedNotification::class);
        Notification::assertNotSentTo($requester, RequestRejectedNotification::class);
        Notification::assertCount(1);
    }

    public function test_rejection_notifies_requester_with_actor_and_step_context_once(): void
    {
        Notification::fake();
        [$owner, $workspace, $requester, $type, $workflow] = $this->definition();
        $this->roleStep($workflow, 1, WorkspaceRole::Owner, 'Manager review');
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        Notification::fake();
        $this->authenticate($owner);

        $this->postJson($this->rejectUrl($workspace, $submission->approvals()->firstOrFail()))->assertOk();
        Notification::assertSentTo($requester, RequestRejectedNotification::class,
            function (RequestRejectedNotification $notification) use ($requester, $owner): bool {
                $payload = $notification->toDatabase($requester);
                $this->assertSame('request_rejected', $payload['event']);
                $this->assertSame($owner->id, $payload['actor']['id']);
                $this->assertSame('Manager review', $payload['approval']['workflow_step_name']);

                return true;
            });
        Notification::assertCount(1);
    }

    public function test_cancellation_targets_only_previous_pending_eligible_assignees_and_excludes_actor(): void
    {
        Notification::fake();
        [$owner, $workspace, $requester, $type, $workflow] = $this->definition();
        $otherOwner = User::factory()->create();
        $future = User::factory()->create();
        $this->member($workspace, $otherOwner, WorkspaceRole::Owner);
        $this->member($workspace, $future, WorkspaceRole::Approver);
        $pendingStep = $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $futureStep = $this->roleStep($workflow, 2, WorkspaceRole::Approver);
        $submission = $this->submission($workspace, $type, $requester, status: RequestStatus::Submitted);
        $pending = $this->approval($workspace, $submission, $pendingStep, RequestApprovalStatus::Pending, 1);
        $waiting = $this->approval($workspace, $submission, $futureStep, RequestApprovalStatus::Waiting, 2);
        foreach ([$owner, $otherOwner] as $assignee) {
            RequestApprovalAssignee::factory()->create(['request_approval_id' => $pending, 'user_id' => $assignee]);
        }
        RequestApprovalAssignee::factory()->create(['request_approval_id' => $waiting, 'user_id' => $future]);

        app(CancelRequest::class)->handle($submission, $owner);

        Notification::assertSentTo($otherOwner, RequestCancelledNotification::class);
        Notification::assertNotSentTo([$owner, $future, $requester], RequestCancelledNotification::class);
        Notification::assertCount(1);
        Notification::fake();
        $draft = $this->submission($workspace, $type, $requester);
        app(CancelRequest::class)->handle($draft, $requester);
        Notification::assertNothingSent();
    }

    public function test_collaboration_notifications_are_deduplicated_and_exclude_actor_auditor_and_unrelated_users(): void
    {
        Notification::fake();
        Storage::fake('local');
        [$owner, $workspace, $requester, $type, $workflow] = $this->definition();
        $assigned = User::factory()->create();
        $admin = User::factory()->create();
        $auditor = User::factory()->create();
        $unrelated = User::factory()->create();
        $this->member($workspace, $assigned, WorkspaceRole::Approver);
        $this->member($workspace, $admin, WorkspaceRole::Admin);
        $this->member($workspace, $auditor, WorkspaceRole::Auditor);
        $this->member($workspace, $unrelated, WorkspaceRole::Requester);
        $step = $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $submission = $this->submission($workspace, $type, $requester, status: RequestStatus::Submitted);
        $approval = $this->approval($workspace, $submission, $step, RequestApprovalStatus::Approved, 1);
        foreach ([$owner, $assigned] as $assignee) {
            RequestApprovalAssignee::factory()->create(['request_approval_id' => $approval, 'user_id' => $assignee]);
        }

        app(CreateRequestComment::class)->handle($submission, $requester, 'A note');
        foreach ([$owner, $assigned, $admin] as $recipient) {
            Notification::assertSentTo($recipient, RequestCommentAddedNotification::class);
        }
        Notification::assertNotSentTo([$requester, $auditor, $unrelated], RequestCommentAddedNotification::class);
        Notification::assertCount(3);

        Notification::fake();
        app(StoreRequestAttachment::class)->handle(
            $submission,
            $assigned,
            UploadedFile::fake()->create('quote.pdf', 2, 'application/pdf'),
        );
        foreach ([$requester, $owner, $admin] as $recipient) {
            Notification::assertSentTo($recipient, RequestAttachmentUploadedNotification::class,
                function (RequestAttachmentUploadedNotification $notification) use ($recipient): bool {
                    $payload = $notification->toDatabase($recipient);
                    $this->assertSame('attachment_uploaded', $payload['event']);
                    $this->assertSame('quote.pdf', $payload['attachment']['original_name']);
                    $this->assertArrayNotHasKey('disk', $payload['attachment']);
                    $this->assertArrayNotHasKey('path', $payload['attachment']);

                    return true;
                });
        }
        Notification::assertNotSentTo([$assigned, $auditor, $unrelated], RequestAttachmentUploadedNotification::class);
        Notification::assertCount(3);
    }

    public function test_notification_classes_are_queued_after_commit_with_bounded_retry_and_no_cta(): void
    {
        $basePayload = [
            'message' => 'Purchase Request #1 was approved.',
            'workspace' => ['id' => 1, 'name' => 'Operations'],
            'request' => ['id' => 1, 'request_type' => ['id' => 2, 'name' => 'Purchase Request']],
            'approval' => ['id' => 3, 'position' => 1, 'workflow_step_name' => 'Manager'],
            'actor' => ['id' => 4, 'name' => 'Ahmed'],
            'comment' => ['id' => 5],
            'attachment' => [
                'id' => 6,
                'original_name' => 'quote.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1024,
            ],
        ];
        $notifications = [
            new ApprovalAssignedNotification(['event' => 'approval_assigned', ...$basePayload]),
            new RequestApprovedNotification(['event' => 'request_approved', ...$basePayload]),
            new RequestRejectedNotification(['event' => 'request_rejected', ...$basePayload]),
            new RequestCancelledNotification(['event' => 'request_cancelled', ...$basePayload]),
            new RequestCommentAddedNotification(['event' => 'comment_added', ...$basePayload]),
            new RequestAttachmentUploadedNotification(['event' => 'attachment_uploaded', ...$basePayload]),
        ];

        foreach ($notifications as $notification) {
            $this->assertInstanceOf(ShouldQueue::class, $notification);
            $this->assertTrue($notification->afterCommit);
            $this->assertSame(3, $notification->tries);
            $this->assertSame([10, 30, 60], $notification->backoff());
            $this->assertSame(['database', 'mail'], $notification->via(User::factory()->make()));
            $this->assertNull($notification->toMail(User::factory()->make())->actionUrl);
            $serialized = serialize($notification);
            $this->assertInstanceOf($notification::class, unserialize($serialized));
            $this->assertStringNotContainsString(RequestSubmission::class, $serialized);
        }
    }

    /** @return array{User, Workspace, User, RequestType, Workflow} */
    private function definition(): array
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
        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $type,
            'created_by' => $owner,
            'status' => WorkflowStatus::Active,
            'draft_guard' => null,
            'active_guard' => 1,
            'published_at' => now(),
        ]);

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

    private function roleStep(Workflow $workflow, int $position, WorkspaceRole $role, string $name = 'Approval'): WorkflowStep
    {
        return WorkflowStep::factory()->create([
            'workflow_id' => $workflow,
            'position' => $position,
            'name' => $name,
            'approver_type' => 'role',
            'approver_role' => $role,
            'approver_user_id' => null,
        ]);
    }

    private function userStep(Workflow $workflow, int $position, User $user): WorkflowStep
    {
        return WorkflowStep::factory()->create([
            'workflow_id' => $workflow,
            'position' => $position,
            'approver_type' => 'user',
            'approver_role' => null,
            'approver_user_id' => $user,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function submission(
        Workspace $workspace,
        RequestType $type,
        User $creator,
        array $payload = [],
        RequestStatus $status = RequestStatus::Draft,
    ): RequestSubmission {
        return RequestSubmission::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $type,
            'created_by' => $creator,
            'payload' => $payload,
            'status' => $status,
        ]);
    }

    private function approval(
        Workspace $workspace,
        RequestSubmission $submission,
        WorkflowStep $step,
        RequestApprovalStatus $status,
        int $position,
    ): RequestApproval {
        return RequestApproval::factory()->create([
            'workspace_id' => $workspace,
            'request_submission_id' => $submission,
            'workflow_step_id' => $step,
            'status' => $status,
            'position' => $position,
            'pending_guard' => $status === RequestApprovalStatus::Pending ? 1 : null,
        ]);
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

<?php

namespace Tests\Feature\Api\V1;

use App\Actions\ApproveRequestApproval;
use App\Actions\CreateRequestComment;
use App\Actions\StoreRequestAttachment;
use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestActivityType;
use App\Enums\RequestApprovalStatus;
use App\Enums\RequestStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestApproval;
use App\Models\RequestApprovalAssignee;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\RequestNotificationDispatcher;
use App\Support\WorkspacePermissions;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class NotificationFailureIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true]);

        parent::tearDown();
    }

    public function test_attachment_notification_failure_after_commit_preserves_the_business_result_and_file(): void
    {
        Storage::fake('local');
        [, , $requester, $submission] = $this->definition();
        $this->expectNotificationFailure('attachment notification failed');

        $attachment = app(StoreRequestAttachment::class)->handle(
            $submission,
            $requester,
            UploadedFile::fake()->create('quote.pdf', 2, 'application/pdf'),
        );

        $this->assertDatabaseHas('request_attachments', ['id' => $attachment->id]);
        $this->assertDatabaseHas('request_activities', [
            'request_submission_id' => $submission->id,
            'request_attachment_id' => $attachment->id,
            'type' => RequestActivityType::AttachmentUploaded->value,
        ]);
        Storage::disk($attachment->disk)->assertExists($attachment->path);
    }

    public function test_result_notification_failure_after_commit_preserves_approval_state_and_activity(): void
    {
        [$owner, $workspace, , $submission, $workflow] = $this->definition();
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow,
            'position' => 1,
            'name' => 'Manager review',
            'approver_type' => 'role',
            'approver_role' => WorkspaceRole::Owner,
            'approver_user_id' => null,
        ]);
        $approval = RequestApproval::factory()->create([
            'workspace_id' => $workspace,
            'request_submission_id' => $submission,
            'workflow_step_id' => $step,
            'status' => RequestApprovalStatus::Pending,
            'pending_guard' => 1,
            'activated_at' => now(),
        ]);
        RequestApprovalAssignee::factory()->create([
            'request_approval_id' => $approval,
            'user_id' => $owner,
        ]);
        $this->expectNotificationFailure('result notification failed');

        $result = app(ApproveRequestApproval::class)->handle($approval, $owner);

        $this->assertSame(RequestApprovalStatus::Approved, $result->status);
        $this->assertSame(RequestStatus::Approved, $submission->fresh()->status);
        $this->assertSame(1, $submission->activities()->where('type', RequestActivityType::ApprovalApproved)->count());
        $this->assertSame(1, $submission->activities()->where('type', RequestActivityType::RequestApproved)->count());
        $this->assertSame(1, RequestApproval::query()->whereKey($approval->id)->count());
    }

    public function test_comment_notification_failure_after_commit_preserves_comment_and_activity(): void
    {
        [, , $requester, $submission] = $this->definition();
        $this->expectNotificationFailure('comment notification failed');

        $comment = app(CreateRequestComment::class)->handle($submission, $requester, 'Operational note');

        $this->assertDatabaseHas('request_comments', [
            'id' => $comment->id,
            'body' => 'Operational note',
        ]);
        $this->assertDatabaseHas('request_activities', [
            'request_submission_id' => $submission->id,
            'request_comment_id' => $comment->id,
            'type' => RequestActivityType::CommentAdded->value,
        ]);
    }

    public function test_transaction_rollback_discards_the_registered_notification_operation(): void
    {
        [, , , $submission] = $this->definition();
        Notification::shouldReceive('send')->never();

        try {
            DB::transaction(function () use ($submission): void {
                app(RequestNotificationDispatcher::class)->requestApproved($submission);

                throw new RuntimeException('business transaction failed');
            });
            $this->fail('Expected the transaction to roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('business transaction failed', $exception->getMessage());
        }
    }

    /** @return array{User, Workspace, User, RequestSubmission, Workflow} */
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
        $submission = RequestSubmission::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $type,
            'workflow_id' => $workflow,
            'created_by' => $requester,
            'status' => RequestStatus::Submitted,
            'submitted_at' => now(),
        ]);

        return [$owner, $workspace, $requester, $submission, $workflow];
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

    private function expectNotificationFailure(string $message): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException($message));
        $this->mock(ExceptionHandler::class, function (MockInterface $mock) use ($message): void {
            $mock->shouldReceive('report')->once()->withArgs(
                fn (Throwable $exception): bool => $exception->getMessage() === $message,
            );
        });
    }
}

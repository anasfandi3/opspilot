<?php

namespace Tests\Feature\Api\V1;

use App\Actions\CreateRequestComment;
use App\Actions\StoreRequestAttachment;
use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestActivityType;
use App\Enums\RequestApprovalStatus;
use App\Enums\RequestStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestActivity;
use App\Models\RequestApproval;
use App\Models\RequestApprovalAssignee;
use App\Models\RequestAttachment;
use App\Models\RequestComment;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\RequestActivityRecorder;
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RequestCollaborationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_creator_can_add_trimmed_immutable_comments_with_validation(): void
    {
        [$owner, $workspace, $requester, $type] = $this->definition();
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);

        $response = $this->postJson($this->commentsUrl($workspace, $submission), [
            'body' => '  Finance confirmed the vendor quote.  ',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'Finance confirmed the vendor quote.')
            ->assertJsonPath('data.author.id', $requester->id);

        $comment = RequestComment::query()->findOrFail($response->json('data.id'));
        $this->assertSame('Finance confirmed the vendor quote.', $comment->body);
        $this->assertDatabaseHas('request_activities', [
            'request_comment_id' => $comment->id,
            'actor_id' => $requester->id,
            'type' => RequestActivityType::CommentAdded->value,
        ]);
        $this->postJson($this->commentsUrl($workspace, $submission), ['body' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson($this->commentsUrl($workspace, $submission), ['body' => str_repeat('a', 5001)])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->patchJson($this->commentsUrl($workspace, $submission)."/{$comment->id}", ['body' => 'changed'])
            ->assertNotFound();
        $this->deleteJson($this->commentsUrl($workspace, $submission)."/{$comment->id}")
            ->assertNotFound();
        $this->assertModelExists($owner);
    }

    public function test_comments_are_allowed_for_participants_in_every_request_status(): void
    {
        [, $workspace, $requester, $type] = $this->definition();
        $this->authenticate($requester);

        foreach (RequestStatus::cases() as $status) {
            $submission = $this->submission($workspace, $type, $requester, $status);
            $this->postJson($this->commentsUrl($workspace, $submission), ['body' => "Note for {$status->value}"])
                ->assertCreated();
        }

        $this->assertSame(count(RequestStatus::cases()), RequestComment::query()->count());
    }

    public function test_only_creator_assigned_approver_owner_and_admin_may_collaborate(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->definition();
        $submission = $this->submission($workspace, $type, $requester, RequestStatus::Submitted);
        $assigned = User::factory()->create();
        $admin = User::factory()->create();
        $auditor = User::factory()->create();
        $unrelated = User::factory()->create();
        $removed = User::factory()->create();
        $this->member($workspace, $assigned, WorkspaceRole::Approver);
        $this->member($workspace, $admin, WorkspaceRole::Admin);
        $this->member($workspace, $auditor, WorkspaceRole::Auditor);
        $this->member($workspace, $unrelated, WorkspaceRole::Approver);
        $this->member($workspace, $removed, WorkspaceRole::Approver);
        $step = $this->roleStep($workflow, 1, WorkspaceRole::Approver);
        $approval = RequestApproval::factory()->create([
            'workspace_id' => $workspace,
            'request_submission_id' => $submission,
            'workflow_step_id' => $step,
            'status' => RequestApprovalStatus::Pending,
            'pending_guard' => 1,
        ]);
        foreach ([$assigned, $removed] as $user) {
            RequestApprovalAssignee::factory()->create(['request_approval_id' => $approval, 'user_id' => $user]);
        }
        WorkspaceMembership::query()->whereBelongsTo($workspace)->whereBelongsTo($removed)->delete();

        foreach ([$requester, $assigned, $owner, $admin] as $index => $participant) {
            $this->authenticate($participant);
            $this->postJson($this->commentsUrl($workspace, $submission), ['body' => "Allowed {$index}"])
                ->assertCreated();
        }

        $this->authenticate($auditor);
        $this->getJson($this->commentsUrl($workspace, $submission))->assertOk();
        $this->postJson($this->commentsUrl($workspace, $submission), ['body' => 'No'])->assertForbidden();
        foreach ([$unrelated, $removed] as $user) {
            $this->authenticate($user);
            $this->postJson($this->commentsUrl($workspace, $submission), ['body' => 'No'])->assertForbidden();
        }
        $this->assertSame(4, $submission->comments()->count());
    }

    public function test_comment_listing_is_newest_first_paginated_and_tenant_scoped(): void
    {
        [, $workspace, $requester, $type] = $this->definition();
        $submission = $this->submission($workspace, $type, $requester);
        $comments = collect([3, 2, 1])->map(fn (int $minutes): RequestComment => RequestComment::factory()->create([
            'workspace_id' => $workspace,
            'request_submission_id' => $submission,
            'author_id' => $requester,
            'created_at' => now()->subMinutes($minutes),
        ]));
        [, $otherWorkspace] = $this->definition();
        $this->authenticate($requester);

        $this->getJson($this->commentsUrl($workspace, $submission).'?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', $comments[2]->id)
            ->assertJsonPath('data.1.id', $comments[1]->id);
        $this->getJson($this->commentsUrl($otherWorkspace, $submission))->assertNotFound();
    }

    public function test_allowed_private_attachments_use_generated_paths_and_hide_storage_fields(): void
    {
        Storage::fake('local');
        [, $workspace, $requester, $type] = $this->definition();
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);

        foreach ([
            UploadedFile::fake()->create('vendor-quote.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->image('site-photo.png'),
        ] as $file) {
            $this->post($this->attachmentsUrl($workspace, $submission), ['file' => $file], ['Accept' => 'application/json'])
                ->assertCreated()->assertJsonMissingPath('data.disk')->assertJsonMissingPath('data.path');
        }

        $attachments = $submission->attachments()->get();
        $this->assertCount(2, $attachments);
        foreach ($attachments as $attachment) {
            $this->assertMatchesRegularExpression(
                "#^requests/{$workspace->id}/{$submission->id}/[0-9a-f-]{36}$#",
                $attachment->path,
            );
            $this->assertStringNotContainsString($attachment->original_name, $attachment->path);
            Storage::disk('local')->assertExists($attachment->path);
        }
        $this->getJson($this->attachmentsUrl($workspace, $submission).'?per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $attachments[1]->id)
            ->assertJsonMissingPath('data.0.disk')->assertJsonMissingPath('data.0.path');
    }

    public function test_executable_script_archive_and_oversized_uploads_are_rejected(): void
    {
        Storage::fake('local');
        [, $workspace, $requester, $type] = $this->definition();
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);

        foreach ([
            ['payload.php', 'application/x-php'],
            ['page.html', 'text/html'],
            ['script.js', 'application/javascript'],
            ['program.exe', 'application/x-msdownload'],
            ['archive.zip', 'application/zip'],
        ] as [$name, $mime]) {
            $this->post($this->attachmentsUrl($workspace, $submission), [
                'file' => UploadedFile::fake()->create($name, 1, $mime),
            ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
        }

        config(['filesystems.attachments.max_kb' => 1]);
        $this->post($this->attachmentsUrl($workspace, $submission), [
            'file' => UploadedFile::fake()->create('large.pdf', 2, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(0, RequestAttachment::query()->count());
    }

    public function test_authorized_download_works_and_missing_or_cross_nested_files_are_blocked(): void
    {
        Storage::fake('local');
        [, $workspace, $requester, $type] = $this->definition();
        $submission = $this->submission($workspace, $type, $requester);
        $otherSubmission = $this->submission($workspace, $type, $requester);
        $attachment = $this->attachment($workspace, $submission, $requester, 'requests/file-one');
        $otherAttachment = $this->attachment($workspace, $otherSubmission, $requester, 'requests/file-two');
        Storage::disk('local')->put($attachment->path, 'private contents');
        Storage::disk('local')->put($otherAttachment->path, 'other contents');
        $this->authenticate($requester);

        $this->get($this->downloadUrl($workspace, $submission, $attachment))->assertOk()
            ->assertDownload('quote.pdf');
        $this->getJson($this->downloadUrl($workspace, $submission, $otherAttachment))->assertNotFound();
        Storage::disk('local')->delete($attachment->path);
        $this->getJson($this->downloadUrl($workspace, $submission, $attachment))->assertNotFound();

        $outsider = User::factory()->create();
        $this->authenticate($outsider);
        $this->getJson($this->downloadUrl($workspace, $submission, $attachment))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken('invalid');
        $this->post($this->attachmentsUrl($workspace, $submission), [
            'file' => UploadedFile::fake()->create('quote.pdf', 1, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->getJson($this->downloadUrl($workspace, $submission, $attachment))->assertUnauthorized();
    }

    public function test_attachment_database_failure_rolls_back_row_activity_and_stored_file(): void
    {
        Storage::fake('local');
        [, $workspace, $requester, $type] = $this->definition();
        $submission = $this->submission($workspace, $type, $requester);
        $this->mock(RequestActivityRecorder::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('database phase failed'));
        });

        try {
            app(StoreRequestAttachment::class)->handle(
                $submission,
                $requester,
                UploadedFile::fake()->create('quote.pdf', 1, 'application/pdf'),
            );
            $this->fail('Expected attachment persistence to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('database phase failed', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, RequestAttachment::query()->count());
        $this->assertSame(0, RequestActivity::query()->count());
    }

    public function test_comment_activity_failure_rolls_back_both_comment_and_activity(): void
    {
        [, $workspace, $requester, $type] = $this->definition();
        $submission = $this->submission($workspace, $type, $requester);
        $this->mock(RequestActivityRecorder::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('comment audit failed'));
        });

        try {
            app(CreateRequestComment::class)->handle($submission, $requester, 'Will roll back');
            $this->fail('Expected comment persistence to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('comment audit failed', $exception->getMessage());
        }

        $this->assertSame(0, RequestComment::query()->count());
        $this->assertSame(0, RequestActivity::query()->count());
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

    private function roleStep(Workflow $workflow, int $position, WorkspaceRole $role): WorkflowStep
    {
        return WorkflowStep::factory()->create([
            'workflow_id' => $workflow,
            'position' => $position,
            'approver_type' => 'role',
            'approver_role' => $role,
            'approver_user_id' => null,
        ]);
    }

    private function submission(
        Workspace $workspace,
        RequestType $type,
        User $creator,
        RequestStatus $status = RequestStatus::Draft,
    ): RequestSubmission {
        return RequestSubmission::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $type,
            'created_by' => $creator,
            'status' => $status,
        ]);
    }

    private function attachment(
        Workspace $workspace,
        RequestSubmission $submission,
        User $uploader,
        string $path,
    ): RequestAttachment {
        return RequestAttachment::factory()->create([
            'workspace_id' => $workspace,
            'request_submission_id' => $submission,
            'uploaded_by' => $uploader,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'quote.pdf',
            'mime_type' => 'application/pdf',
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

    private function commentsUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return $this->requestUrl($workspace, $submission).'/comments';
    }

    private function attachmentsUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return $this->requestUrl($workspace, $submission).'/attachments';
    }

    private function downloadUrl(
        Workspace $workspace,
        RequestSubmission $submission,
        RequestAttachment $attachment,
    ): string {
        return $this->attachmentsUrl($workspace, $submission)."/{$attachment->id}/download";
    }
}

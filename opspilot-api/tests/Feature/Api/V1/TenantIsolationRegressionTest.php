<?php

namespace Tests\Feature\Api\V1;

use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestApprovalStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestApproval;
use App\Models\RequestAttachment;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_nested_resources_cannot_be_rebound_through_another_workspace(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $workspaceA = Workspace::factory()->create(['owner_id' => $ownerA]);
        $workspaceB = Workspace::factory()->create(['owner_id' => $ownerB]);
        $this->member($workspaceA, $ownerA, WorkspaceRole::Owner);
        $this->member($workspaceB, $ownerB, WorkspaceRole::Owner);
        $typeA = RequestType::factory()->create(['workspace_id' => $workspaceA, 'created_by' => $ownerA]);
        $typeB = RequestType::factory()->create(['workspace_id' => $workspaceB, 'created_by' => $ownerB]);
        $workflowB = Workflow::factory()->create(['workspace_id' => $workspaceB, 'request_type_id' => $typeB, 'created_by' => $ownerB]);
        $stepB = WorkflowStep::factory()->create(['workflow_id' => $workflowB]);
        $requestA = RequestSubmission::factory()->create(['workspace_id' => $workspaceA, 'request_type_id' => $typeA, 'created_by' => $ownerA]);
        $requestB = RequestSubmission::factory()->create(['workspace_id' => $workspaceB, 'request_type_id' => $typeB, 'workflow_id' => $workflowB, 'created_by' => $ownerB]);
        $approvalB = RequestApproval::factory()->create([
            'workspace_id' => $workspaceB,
            'request_submission_id' => $requestB,
            'workflow_step_id' => $stepB,
            'status' => RequestApprovalStatus::Pending,
            'pending_guard' => 1,
        ]);
        $attachmentB = RequestAttachment::factory()->create([
            'workspace_id' => $workspaceB,
            'request_submission_id' => $requestB,
            'uploaded_by' => $ownerB,
        ]);
        $this->authenticate($ownerA, $workspaceA);

        $this->getJson("/api/v1/workspaces/{$workspaceA->id}/request-types/{$typeB->id}")->assertNotFound();
        $this->getJson("/api/v1/workspaces/{$workspaceA->id}/request-types/{$typeA->id}/workflows/{$workflowB->id}")->assertNotFound();
        $this->getJson("/api/v1/workspaces/{$workspaceA->id}/requests/{$requestB->id}")->assertNotFound();
        $this->getJson("/api/v1/workspaces/{$workspaceA->id}/approvals/{$approvalB->id}")->assertNotFound();
        $this->getJson("/api/v1/workspaces/{$workspaceA->id}/requests/{$requestA->id}/attachments/{$attachmentB->id}/download")->assertNotFound();
        $this->getJson("/api/v1/workspaces/{$workspaceA->id}/reports/requests?request_type_id={$typeB->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('request_type_id');
    }

    public function test_workspace_scoped_report_permission_does_not_leak_between_team_contexts(): void
    {
        $user = User::factory()->create();
        $workspaceA = Workspace::factory()->create();
        $workspaceB = Workspace::factory()->create();
        $this->member($workspaceA, $user, WorkspaceRole::Auditor);
        $this->member($workspaceB, $user, WorkspaceRole::Requester);
        $this->authenticate($user, $workspaceA);

        $this->getJson("/api/v1/workspaces/{$workspaceA->id}/reports/requests")->assertOk();
        $this->getJson("/api/v1/workspaces/{$workspaceB->id}/reports/requests")->assertForbidden();
        $this->getJson("/api/v1/workspaces/{$workspaceB->id}/dashboard")->assertForbidden();
        $this->assertSame($workspaceA->id, $user->fresh()->current_workspace_id);
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

    private function authenticate(User $user, Workspace $workspace): void
    {
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }
}

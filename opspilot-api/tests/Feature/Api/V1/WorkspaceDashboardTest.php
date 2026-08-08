<?php

namespace Tests\Feature\Api\V1;

use App\Actions\SynchronizeWorkspacePermissions;
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
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_tenant_scoped_current_metrics_and_safe_recent_requests(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);
        $auditor = User::factory()->create();
        $this->member($workspace, $auditor, WorkspaceRole::Auditor);
        $activeA = $this->requestType($workspace, $owner, true);
        $activeB = $this->requestType($workspace, $owner, true);
        $this->requestType($workspace, $owner, false);
        $requests = [];
        foreach ([
            RequestStatus::Draft,
            RequestStatus::Submitted,
            RequestStatus::Approved,
            RequestStatus::Rejected,
            RequestStatus::Cancelled,
            RequestStatus::Draft,
        ] as $index => $status) {
            $requests[] = RequestSubmission::factory()->create([
                'workspace_id' => $workspace,
                'request_type_id' => $index % 2 === 0 ? $activeA : $activeB,
                'created_by' => $owner,
                'status' => $status,
                'payload' => ['secret' => 'must not leak'],
                'definition_snapshot' => ['private' => true],
                'created_at' => '2026-08-0'.($index + 1).' 10:00:00',
            ]);
        }
        $requests[4]->forceFill(['created_at' => '2026-08-06 10:00:00'])->save();
        $step = $this->step($workspace, $activeA, $owner);
        $pending = RequestApproval::factory()->create([
            'workspace_id' => $workspace,
            'request_submission_id' => $requests[1],
            'workflow_step_id' => $step,
            'status' => RequestApprovalStatus::Pending,
            'pending_guard' => 1,
            'activated_at' => now(),
        ]);
        foreach (User::factory()->count(3)->create() as $assignee) {
            RequestApprovalAssignee::factory()->create([
                'request_approval_id' => $pending,
                'user_id' => $assignee,
            ]);
        }

        $foreignOwner = User::factory()->create();
        $foreignWorkspace = $this->workspace($foreignOwner);
        $foreignType = $this->requestType($foreignWorkspace, $foreignOwner, true);
        RequestSubmission::factory()->create([
            'workspace_id' => $foreignWorkspace,
            'request_type_id' => $foreignType,
            'created_by' => $foreignOwner,
            'status' => RequestStatus::Approved,
        ]);

        $this->authenticate($auditor, $workspace);
        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.requests.total', 6)
            ->assertJsonPath('data.requests.draft', 2)
            ->assertJsonPath('data.requests.submitted', 1)
            ->assertJsonPath('data.requests.approved', 1)
            ->assertJsonPath('data.requests.rejected', 1)
            ->assertJsonPath('data.requests.cancelled', 1)
            ->assertJsonPath('data.approvals.pending', 1)
            ->assertJsonPath('data.request_types.active', 2)
            ->assertJsonPath('data.members.total', 2)
            ->assertJsonCount(5, 'data.recent_requests')
            ->assertJsonMissingPath('data.recent_requests.0.payload')
            ->assertJsonMissingPath('data.recent_requests.0.definition_snapshot');

        $this->assertSame(
            [$requests[5]->id, $requests[4]->id, $requests[3]->id, $requests[2]->id, $requests[1]->id],
            collect($response->json('data.recent_requests'))->pluck('id')->all(),
        );
    }

    private function workspace(User $owner): Workspace
    {
        $workspace = Workspace::factory()->create(['owner_id' => $owner]);
        $this->member($workspace, $owner, WorkspaceRole::Owner);

        return $workspace;
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

    private function requestType(Workspace $workspace, User $creator, bool $active): RequestType
    {
        return RequestType::factory()->create([
            'workspace_id' => $workspace,
            'created_by' => $creator,
            'is_active' => $active,
        ]);
    }

    private function step(Workspace $workspace, RequestType $type, User $creator): WorkflowStep
    {
        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $type,
            'created_by' => $creator,
            'status' => WorkflowStatus::Active,
            'draft_guard' => null,
            'active_guard' => 1,
            'published_at' => now(),
        ]);

        return WorkflowStep::factory()->create(['workflow_id' => $workflow]);
    }

    private function authenticate(User $user, Workspace $workspace): void
    {
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        $this->withToken($user->createToken('test')->plainTextToken);
    }
}

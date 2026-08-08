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

class ApprovalReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_report_returns_current_pending_and_period_decisions_with_durations_trends_and_steps(): void
    {
        [$owner, $workspace, $typeA] = $this->definition();
        $typeB = $this->requestType($workspace, $owner, 'Facilities');
        $stepA = $this->step($workspace, $typeA, $owner, 'Manager review');
        $stepB = $this->step($workspace, $typeB, $owner, 'Manager review');

        $oldest = $this->approval($workspace, $typeA, $owner, $stepA, RequestApprovalStatus::Pending, '2026-07-20 10:00:00');
        $this->approval($workspace, $typeB, $owner, $stepB, RequestApprovalStatus::Pending, '2026-08-01 10:00:00');
        foreach (User::factory()->count(3)->create() as $assignee) {
            RequestApprovalAssignee::factory()->create(['request_approval_id' => $oldest, 'user_id' => $assignee]);
        }

        $this->approval($workspace, $typeA, $owner, $stepA, RequestApprovalStatus::Approved, '2026-08-01 08:00:00', '2026-08-01 12:00:00');
        $this->approval($workspace, $typeA, $owner, $stepA, RequestApprovalStatus::Approved, '2026-08-02 08:00:00', '2026-08-02 16:00:00');
        $this->approval($workspace, $typeB, $owner, $stepB, RequestApprovalStatus::Rejected, '2026-08-03 00:00:00', '2026-08-03 12:00:00');
        foreach ([RequestApprovalStatus::Waiting, RequestApprovalStatus::Skipped, RequestApprovalStatus::Cancelled] as $status) {
            $this->approval($workspace, $typeA, $owner, $stepA, $status, '2026-08-01 08:00:00', '2026-08-01 09:00:00');
        }

        $foreignOwner = User::factory()->create();
        $foreignWorkspace = $this->workspace($foreignOwner);
        $foreignType = $this->requestType($foreignWorkspace, $foreignOwner, 'Foreign');
        $foreignStep = $this->step($foreignWorkspace, $foreignType, $foreignOwner, 'Manager review');
        $this->approval($foreignWorkspace, $foreignType, $foreignOwner, $foreignStep, RequestApprovalStatus::Approved, '2026-08-01 08:00:00', '2026-08-01 09:00:00');

        $this->authenticate($owner, $workspace);
        $this->getJson($this->url($workspace).'?from=2026-08-01&to=2026-08-03')
            ->assertOk()
            ->assertJsonPath('data.current.pending', 2)
            ->assertJsonPath('data.current.oldest_pending_activated_at', '2026-07-20T10:00:00.000000Z')
            ->assertJsonPath('data.decisions.total', 3)
            ->assertJsonPath('data.decisions.approved', 2)
            ->assertJsonPath('data.decisions.rejected', 1)
            ->assertJsonPath('data.decisions.average_decision_hours', 8)
            ->assertJsonPath('data.decisions.approved_average_hours', 6)
            ->assertJsonPath('data.decisions.rejected_average_hours', 12)
            ->assertJsonCount(3, 'data.decisions.trend')
            ->assertJsonPath('data.decisions.trend.0.approved', 1)
            ->assertJsonPath('data.decisions.trend.0.rejected', 0)
            ->assertJsonPath('data.decisions.trend.0.total', 1)
            ->assertJsonPath('data.decisions.trend.1.total', 1)
            ->assertJsonPath('data.decisions.trend.2.rejected', 1)
            ->assertJsonCount(2, 'data.decisions.by_step')
            ->assertJsonPath('data.decisions.by_step.0.workflow_step.id', $stepA->id)
            ->assertJsonPath('data.decisions.by_step.0.workflow_step.name', 'Manager review')
            ->assertJsonPath('data.decisions.by_step.0.total', 2)
            ->assertJsonPath('data.decisions.by_step.1.workflow_step.id', $stepB->id)
            ->assertJsonPath('data.decisions.by_step.1.workflow_step.name', 'Manager review')
            ->assertJsonPath('data.decisions.by_step.1.total', 1);
    }

    public function test_approval_report_request_type_filter_and_empty_decision_averages(): void
    {
        [$owner, $workspace, $typeA] = $this->definition();
        $typeB = $this->requestType($workspace, $owner, 'Facilities');
        $stepA = $this->step($workspace, $typeA, $owner, 'A');
        $stepB = $this->step($workspace, $typeB, $owner, 'B');
        $this->approval($workspace, $typeA, $owner, $stepA, RequestApprovalStatus::Pending, '2026-08-01 08:00:00');
        $this->approval($workspace, $typeB, $owner, $stepB, RequestApprovalStatus::Pending, '2026-08-01 09:00:00');
        $this->approval($workspace, $typeA, $owner, $stepA, RequestApprovalStatus::Approved, '2026-08-01 08:00:00', '2026-08-01 10:00:00');
        $this->approval($workspace, $typeB, $owner, $stepB, RequestApprovalStatus::Rejected, '2026-08-01 08:00:00', '2026-08-01 12:00:00');
        $this->authenticate($owner, $workspace);

        $this->getJson($this->url($workspace).'?from=2026-08-01&to=2026-08-02&request_type_id='.$typeA->id)
            ->assertOk()
            ->assertJsonPath('data.current.pending', 1)
            ->assertJsonPath('data.decisions.total', 1)
            ->assertJsonPath('data.decisions.approved', 1)
            ->assertJsonPath('data.decisions.rejected', 0)
            ->assertJsonCount(1, 'data.decisions.by_step');

        $this->getJson($this->url($workspace).'?from=2026-08-10&to=2026-08-11')
            ->assertOk()
            ->assertJsonPath('data.current.pending', 2)
            ->assertJsonPath('data.decisions.total', 0)
            ->assertJsonPath('data.decisions.average_decision_hours', null)
            ->assertJsonPath('data.decisions.approved_average_hours', null)
            ->assertJsonPath('data.decisions.rejected_average_hours', null)
            ->assertJsonCount(0, 'data.decisions.by_step');
    }

    public function test_empty_approval_report_returns_zero_pending_and_null_oldest_activation(): void
    {
        [$owner, $workspace] = $this->definition();
        $this->authenticate($owner, $workspace);

        $this->getJson($this->url($workspace).'?from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertJsonPath('data.current.pending', 0)
            ->assertJsonPath('data.current.oldest_pending_activated_at', null)
            ->assertJsonPath('data.decisions.total', 0)
            ->assertJsonPath('data.decisions.average_decision_hours', null);
    }

    /** @return array{User, Workspace, RequestType} */
    private function definition(): array
    {
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);

        return [$owner, $workspace, $this->requestType($workspace, $owner, 'Purchasing')];
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
        WorkspaceMembership::query()->firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $user->id], ['joined_at' => now()]);
        app(WorkspacePermissions::class)->assign($user, $workspace, $role);
    }

    private function requestType(Workspace $workspace, User $creator, string $name): RequestType
    {
        return RequestType::factory()->create(['workspace_id' => $workspace, 'created_by' => $creator, 'name' => $name]);
    }

    private function step(Workspace $workspace, RequestType $type, User $creator, string $name): WorkflowStep
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

        return WorkflowStep::factory()->create(['workflow_id' => $workflow, 'name' => $name]);
    }

    private function approval(
        Workspace $workspace,
        RequestType $type,
        User $creator,
        WorkflowStep $step,
        RequestApprovalStatus $status,
        ?string $activatedAt,
        ?string $decidedAt = null,
    ): RequestApproval {
        $submission = RequestSubmission::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $type,
            'created_by' => $creator,
            'status' => RequestStatus::Submitted,
        ]);

        return RequestApproval::factory()->create([
            'workspace_id' => $workspace,
            'request_submission_id' => $submission,
            'workflow_step_id' => $step,
            'status' => $status,
            'pending_guard' => $status === RequestApprovalStatus::Pending ? 1 : null,
            'activated_at' => $activatedAt,
            'decided_at' => $decidedAt,
        ]);
    }

    private function authenticate(User $user, Workspace $workspace): void
    {
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    private function url(Workspace $workspace): string
    {
        return "/api/v1/workspaces/{$workspace->id}/reports/approvals";
    }
}

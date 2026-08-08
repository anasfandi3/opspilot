<?php

namespace Tests\Feature\Api\V1;

use App\Actions\PublishWorkflow;
use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestApprovalStatus;
use App\Enums\RequestFieldType;
use App\Enums\RequestStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestApproval;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_creates_ordered_runtime_approvals_assignments_and_one_pending_step(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $approver = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $amount = $this->field($type, 'amount', RequestFieldType::Number);
        $first = $this->roleStep($workflow, 1, WorkspaceRole::Owner, 'Manager');
        $skipped = $this->roleStep($workflow, 2, WorkspaceRole::Approver, 'Finance');
        $this->condition($skipped, $amount, 'greater_than', 1000);
        $third = $this->userStep($workflow, 3, $approver, 'Director');
        $submission = $this->submission($workspace, $type, $requester, ['amount' => 500]);

        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.approvals.0.status', 'pending')
            ->assertJsonPath('data.approvals.1.status', 'skipped')
            ->assertJsonPath('data.approvals.2.status', 'waiting');

        $approvals = $submission->approvals()->with('assignees')->get();
        $this->assertSame([$first->id, $skipped->id, $third->id], $approvals->pluck('workflow_step_id')->all());
        $this->assertSame([1, 2, 3], $approvals->pluck('position')->all());
        $this->assertSame([1, 0, 1], $approvals->map->assignees->map->count()->all());
        $this->assertSame($owner->id, $approvals[0]->assignees->sole()->user_id);
        $this->assertSame($approver->id, $approvals[2]->assignees->sole()->user_id);
        $this->assertSame(1, $submission->approvals()->where('status', 'pending')->count());
        $this->assertNotNull($approvals[0]->activated_at);
    }

    public function test_all_skipped_steps_immediately_approve_request_without_resolving_invalid_assignees(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $foreign = User::factory()->create();
        $amount = $this->field($type, 'amount', RequestFieldType::Number);
        $step = $this->userStep($workflow, 1, $foreign);
        $this->condition($step, $amount, 'greater_than', 1000);
        $submission = $this->submission($workspace, $type, $requester, ['amount' => 10]);
        $this->authenticate($requester);

        $this->postJson($this->submitUrl($workspace, $submission))->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approvals.0.status', 'skipped');
        $submission->refresh();
        $this->assertNotNull($submission->resolved_at);
        $this->assertSame(0, $submission->approvals()->where('status', 'pending')->count());
        $this->assertSame(0, $submission->approvals()->firstOrFail()->assignees()->count());
    }

    public function test_zero_step_workflow_and_assignment_failure_roll_back_entire_submission(): void
    {
        [$owner, $workspace, $requester, $type] = $this->setupDefinition(createWorkflow: false);
        $emptyWorkflow = $this->activeWorkflow($type, $owner);
        $emptySubmission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $emptySubmission))->assertUnprocessable()
            ->assertJsonValidationErrors('workflow');
        $this->assertDraftRollback($emptySubmission);

        $emptyWorkflow->forceFill(['status' => WorkflowStatus::Archived, 'active_guard' => null])->save();
        $workflow = $this->activeWorkflow($type, $owner, 2);
        $this->roleStep($workflow, 1, WorkspaceRole::Approver);
        $unassignedSubmission = $this->submission($workspace, $type, $requester);
        $this->postJson($this->submitUrl($workspace, $unassignedSubmission))->assertUnprocessable()
            ->assertJsonValidationErrors('workflow');
        $this->assertDraftRollback($unassignedSubmission);
    }

    public function test_specific_user_assignment_requires_current_membership_and_approval_permission(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $eligible = User::factory()->create();
        $this->member($workspace, $eligible, WorkspaceRole::Approver);
        $this->userStep($workflow, 1, $eligible);
        $valid = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $valid))->assertOk();
        $this->assertSame($eligible->id, $valid->approvals()->firstOrFail()->assignees()->sole()->user_id);

        $workflow->forceFill(['status' => WorkflowStatus::Archived, 'active_guard' => null])->save();
        $foreignWorkflow = $this->activeWorkflow($type, $owner, 2);
        $foreign = User::factory()->create();
        $this->userStep($foreignWorkflow, 1, $foreign);
        $foreignSubmission = $this->submission($workspace, $type, $requester);
        $this->postJson($this->submitUrl($workspace, $foreignSubmission))->assertUnprocessable();
        $this->assertDraftRollback($foreignSubmission);

        $foreignWorkflow->forceFill(['status' => WorkflowStatus::Archived, 'active_guard' => null])->save();
        $requesterWorkflow = $this->activeWorkflow($type, $owner, 3);
        $this->userStep($requesterWorkflow, 1, $requester);
        $noPermission = $this->submission($workspace, $type, $requester);
        $this->postJson($this->submitUrl($workspace, $noPermission))->assertUnprocessable();
        $this->assertDraftRollback($noPermission);
    }

    public function test_role_assignment_snapshots_all_and_only_eligible_scoped_role_members(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $admin = User::factory()->create();
        $this->member($workspace, $first, WorkspaceRole::Approver);
        $this->member($workspace, $second, WorkspaceRole::Approver);
        $this->member($workspace, $admin, WorkspaceRole::Admin);
        [$foreignOwner, $foreignWorkspace] = $this->setupDefinition();
        $foreignApprover = User::factory()->create();
        $this->member($foreignWorkspace, $foreignApprover, WorkspaceRole::Approver);
        $this->roleStep($workflow, 1, WorkspaceRole::Approver);
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);

        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $assignedIds = $submission->approvals()->firstOrFail()->assignees()->orderBy('user_id')->pluck('user_id')->all();
        $this->assertSame([$first->id, $second->id], $assignedIds);
        $this->assertNotContains($owner->id, $assignedIds);
        $this->assertNotContains($admin->id, $assignedIds);
        $this->assertNotContains($foreignApprover->id, $assignedIds);
        $this->assertSame(count($assignedIds), count(array_unique($assignedIds)));
        $this->assertModelExists($foreignOwner);
    }

    public function test_each_supported_workspace_role_resolves_its_current_eligible_members(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $admin = User::factory()->create();
        $approver = User::factory()->create();
        $this->member($workspace, $admin, WorkspaceRole::Admin);
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $this->roleStep($workflow, 2, WorkspaceRole::Admin);
        $this->roleStep($workflow, 3, WorkspaceRole::Approver);
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);

        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();

        $approvals = $submission->approvals()->with('assignees')->get();
        $this->assertSame($owner->id, $approvals[0]->assignees->sole()->user_id);
        $this->assertSame($admin->id, $approvals[1]->assignees->sole()->user_id);
        $this->assertSame($approver->id, $approvals[2]->assignees->sole()->user_id);
    }

    public function test_inbox_is_assignment_scoped_pending_by_default_filterable_and_paginated(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $approver = User::factory()->create();
        $other = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $this->member($workspace, $other, WorkspaceRole::Approver);
        $this->userStep($workflow, 1, $approver);
        $first = $this->submission($workspace, $type, $requester);
        $second = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $first))->assertOk();
        $this->postJson($this->submitUrl($workspace, $second))->assertOk();

        $this->authenticate($approver);
        $this->getJson($this->inboxUrl($workspace).'?per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonMissingPath('data.0.request.payload');
        $approval = $first->approvals()->firstOrFail();
        $this->postJson($this->approveUrl($workspace, $approval))->assertOk();
        $this->getJson($this->inboxUrl($workspace))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->inboxUrl($workspace).'?status=approved')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $approval->id);

        $this->authenticate($other);
        $this->getJson($this->inboxUrl($workspace))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_approval_inbox_and_show_enforce_permission_assignment_and_workspace_isolation(): void
    {
        [$ownerA, $workspaceA, $requesterA, $typeA, $workflowA] = $this->setupDefinition();
        [$ownerB, $workspaceB, $requesterB, $typeB, $workflowB] = $this->setupDefinition();
        $approverA = User::factory()->create();
        $this->member($workspaceA, $approverA, WorkspaceRole::Approver);
        $this->userStep($workflowA, 1, $approverA);
        $this->roleStep($workflowB, 1, WorkspaceRole::Owner);
        $submissionA = $this->submission($workspaceA, $typeA, $requesterA);
        $submissionB = $this->submission($workspaceB, $typeB, $requesterB);
        $this->authenticate($requesterA);
        $this->postJson($this->submitUrl($workspaceA, $submissionA))->assertOk();
        $this->authenticate($requesterB);
        $this->postJson($this->submitUrl($workspaceB, $submissionB))->assertOk();
        $approvalA = $submissionA->approvals()->firstOrFail();
        $approvalB = $submissionB->approvals()->firstOrFail();

        $this->authenticate($approverA);
        $this->getJson($this->approvalUrl($workspaceA, $approvalA))->assertOk();
        $this->getJson($this->approvalUrl($workspaceA, $approvalB))->assertNotFound();
        $this->getJson($this->approvalUrl($workspaceB, $approvalB))->assertForbidden();

        $this->authenticate($ownerA);
        $this->getJson($this->approvalUrl($workspaceA, $approvalA))->assertForbidden();
        $this->postJson($this->approveUrl($workspaceA, $approvalA))->assertForbidden();
        $this->assertModelExists($ownerB);
    }

    public function test_assigned_approver_can_view_only_assigned_request_even_after_decision(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $approver = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $this->userStep($workflow, 1, $approver);
        $assigned = $this->submission($workspace, $type, $requester);
        $unrelated = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $assigned))->assertOk();
        $this->authenticate($approver);

        $this->getJson($this->requestUrl($workspace, $assigned))->assertOk();
        $this->getJson($this->requestUrl($workspace, $unrelated))->assertForbidden();
        $approval = $assigned->approvals()->firstOrFail();
        $this->postJson($this->approveUrl($workspace, $approval))->assertOk();
        $this->getJson($this->requestUrl($workspace, $assigned))->assertOk();
    }

    public function test_approving_steps_progresses_sequentially_skips_irrelevant_steps_and_resolves_final_request(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $amount = $this->field($type, 'amount', RequestFieldType::Number);
        $firstStep = $this->roleStep($workflow, 1, WorkspaceRole::Owner, 'First');
        $skippedStep = $this->roleStep($workflow, 2, WorkspaceRole::Owner, 'Skipped');
        $this->condition($skippedStep, $amount, 'greater_than', 1000);
        $lastStep = $this->roleStep($workflow, 3, WorkspaceRole::Owner, 'Last');
        $submission = $this->submission($workspace, $type, $requester, ['amount' => 100]);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $this->authenticate($owner);
        $approvals = $submission->approvals()->get();

        $this->postJson($this->approveUrl($workspace, $approvals[0]))->assertOk();
        $approvals = $submission->approvals()->get();
        $this->assertSame(RequestApprovalStatus::Approved, $approvals[0]->status);
        $this->assertSame(RequestApprovalStatus::Skipped, $approvals[1]->status);
        $this->assertSame(RequestApprovalStatus::Pending, $approvals[2]->status);
        $this->assertSame(1, $submission->approvals()->where('status', 'pending')->count());

        $this->postJson($this->approveUrl($workspace, $approvals[2]))->assertOk();
        $submission->refresh();
        $this->assertSame(RequestStatus::Approved, $submission->status);
        $this->assertNotNull($submission->resolved_at);
        $this->assertSame($owner->id, $submission->approvals()->where('workflow_step_id', $firstStep->id)->firstOrFail()->decided_by);
        $this->assertSame($lastStep->id, $approvals[2]->workflow_step_id);
    }

    public function test_non_assignee_removed_member_and_user_without_permission_cannot_approve(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $approver = User::factory()->create();
        $other = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $this->member($workspace, $other, WorkspaceRole::Approver);
        $this->userStep($workflow, 1, $approver);
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $approval = $submission->approvals()->firstOrFail();

        $this->authenticate($other);
        $this->postJson($this->approveUrl($workspace, $approval))->assertForbidden();
        app(WorkspacePermissions::class)->assign($approver, $workspace, WorkspaceRole::Requester);
        $this->authenticate($approver);
        $this->postJson($this->approveUrl($workspace, $approval))->assertForbidden();
        app(WorkspacePermissions::class)->assign($approver, $workspace, WorkspaceRole::Approver);
        app(WorkspacePermissions::class)->remove($approver, $workspace);
        WorkspaceMembership::query()->where('workspace_id', $workspace->id)->where('user_id', $approver->id)->delete();
        $this->postJson($this->approveUrl($workspace, $approval))->assertForbidden();
        $this->assertSame(RequestApprovalStatus::Pending, $approval->fresh()->status);
    }

    public function test_rejection_resolves_request_cancels_later_waiting_and_preserves_earlier_and_skipped_history(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $amount = $this->field($type, 'amount', RequestFieldType::Number);
        $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $this->roleStep($workflow, 2, WorkspaceRole::Owner);
        $this->roleStep($workflow, 3, WorkspaceRole::Owner);
        $skipped = $this->roleStep($workflow, 4, WorkspaceRole::Owner);
        $this->condition($skipped, $amount, 'greater_than', 1000);
        $submission = $this->submission($workspace, $type, $requester, ['amount' => 10]);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $this->authenticate($owner);
        $approvals = $submission->approvals()->get();
        $this->postJson($this->approveUrl($workspace, $approvals[0]))->assertOk();
        $this->postJson($this->rejectUrl($workspace, $approvals[1]))->assertOk();

        $approvals = $submission->approvals()->get();
        $this->assertSame([
            RequestApprovalStatus::Approved,
            RequestApprovalStatus::Rejected,
            RequestApprovalStatus::Cancelled,
            RequestApprovalStatus::Skipped,
        ], $approvals->pluck('status')->all());
        $submission->refresh();
        $this->assertSame(RequestStatus::Rejected, $submission->status);
        $this->assertNotNull($submission->resolved_at);
        $this->assertSame($owner->id, $approvals[1]->decided_by);
    }

    public function test_stale_double_and_conflicting_decisions_fail_without_overwriting_first_result(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $approval = $submission->approvals()->firstOrFail();
        $this->authenticate($owner);

        $this->postJson($this->approveUrl($workspace, $approval))->assertOk();
        $firstDecision = $approval->fresh();
        $this->postJson($this->approveUrl($workspace, $approval))->assertForbidden();
        $this->postJson($this->rejectUrl($workspace, $approval))->assertForbidden();
        $approval->refresh();
        $this->assertSame(RequestApprovalStatus::Approved, $approval->status);
        $this->assertSame($firstDecision->decided_by, $approval->decided_by);
        $this->assertTrue($firstDecision->decided_at->equalTo($approval->decided_at));
        $this->assertSame(RequestStatus::Approved, $submission->fresh()->status);
    }

    public function test_reject_then_approve_fails_and_request_cannot_progress(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $approval = $submission->approvals()->firstOrFail();
        $this->authenticate($owner);

        $this->postJson($this->rejectUrl($workspace, $approval))->assertOk();
        $this->postJson($this->approveUrl($workspace, $approval))->assertForbidden();
        $this->assertSame(RequestApprovalStatus::Rejected, $approval->fresh()->status);
        $this->assertSame(RequestStatus::Rejected, $submission->fresh()->status);
    }

    public function test_submitted_cancellation_closes_open_approvals_and_preserves_completed_skipped_and_history(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $amount = $this->field($type, 'amount', RequestFieldType::Number);
        $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $this->roleStep($workflow, 2, WorkspaceRole::Owner);
        $this->roleStep($workflow, 3, WorkspaceRole::Owner);
        $skipped = $this->roleStep($workflow, 4, WorkspaceRole::Owner);
        $this->condition($skipped, $amount, 'greater_than', 1000);
        $submission = $this->submission($workspace, $type, $requester, ['amount' => 10]);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $snapshot = $submission->fresh()->definition_snapshot;
        $this->authenticate($owner);
        $this->postJson($this->approveUrl($workspace, $submission->approvals()->firstOrFail()))->assertOk();
        $this->authenticate($requester);
        $this->postJson($this->cancelUrl($workspace, $submission))->assertOk();

        $submission->refresh();
        $this->assertSame(RequestStatus::Cancelled, $submission->status);
        $this->assertNull($submission->resolved_at);
        $this->assertNotNull($submission->cancelled_at);
        $this->assertSame($workflow->id, $submission->workflow_id);
        $this->assertSame($snapshot, $submission->definition_snapshot);
        $this->assertSame(['amount' => 10], $submission->payload);
        $this->assertSame([
            RequestApprovalStatus::Approved,
            RequestApprovalStatus::Cancelled,
            RequestApprovalStatus::Cancelled,
            RequestApprovalStatus::Skipped,
        ], $submission->approvals()->pluck('status')->all());

        $this->authenticate($owner);
        $this->postJson($this->approveUrl($workspace, $submission->approvals()->where('position', 2)->firstOrFail()))->assertForbidden();
    }

    public function test_draft_cancellation_creates_no_approvals_and_terminal_requests_cannot_cancel(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $draft = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->cancelUrl($workspace, $draft))->assertOk();
        $this->assertSame(0, $draft->approvals()->count());

        foreach ([RequestStatus::Approved, RequestStatus::Rejected, RequestStatus::Cancelled] as $status) {
            $terminal = $this->submission($workspace, $type, $requester, status: $status);
            $this->postJson($this->cancelUrl($workspace, $terminal))->assertForbidden();
        }
    }

    public function test_runtime_approvals_remain_bound_to_v2_after_v3_and_new_requests_use_v3(): void
    {
        [$owner, $workspace, $requester, $type] = $this->setupDefinition(createWorkflow: false);
        $v2 = $this->activeWorkflow($type, $owner, 2);
        $v2Step = $this->roleStep($v2, 1, WorkspaceRole::Owner, 'V2 approval');
        $existing = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $existing))->assertOk();

        $v3 = $this->draftWorkflow($type, $owner, 3);
        $v3Step = $this->roleStep($v3, 1, WorkspaceRole::Owner, 'V3 approval');
        app(PublishWorkflow::class)->handle($v3);
        $new = $this->submission($workspace, $type, $requester);
        $this->postJson($this->submitUrl($workspace, $new))->assertOk();

        $this->assertSame($v2->id, $existing->fresh()->workflow_id);
        $this->assertSame($v2Step->id, $existing->approvals()->sole()->workflow_step_id);
        $this->assertSame($v3->id, $new->fresh()->workflow_id);
        $this->assertSame($v3Step->id, $new->approvals()->sole()->workflow_step_id);
    }

    public function test_request_detail_contains_ordered_progress_but_summary_list_does_not(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $this->roleStep($workflow, 2, WorkspaceRole::Owner, 'Second');
        $this->roleStep($workflow, 1, WorkspaceRole::Owner, 'First');
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();

        $this->getJson($this->requestUrl($workspace, $submission))->assertOk()
            ->assertJsonPath('data.approvals.0.position', 1)
            ->assertJsonPath('data.approvals.1.position', 2);
        $this->getJson($this->requestsUrl($workspace))->assertOk()
            ->assertJsonMissingPath('data.0.approvals');
    }

    public function test_pending_guard_prevents_two_pending_approvals_for_one_request(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $firstStep = $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $secondStep = $this->roleStep($workflow, 2, WorkspaceRole::Owner);
        $submission = $this->submission($workspace, $type, $requester);
        RequestApproval::factory()->create([
            'workspace_id' => $workspace,
            'request_submission_id' => $submission,
            'workflow_step_id' => $firstStep,
            'position' => 1,
            'status' => RequestApprovalStatus::Pending,
            'pending_guard' => 1,
        ]);

        $this->expectException(QueryException::class);
        RequestApproval::factory()->create([
            'workspace_id' => $workspace,
            'request_submission_id' => $submission,
            'workflow_step_id' => $secondStep,
            'position' => 2,
            'status' => RequestApprovalStatus::Pending,
            'pending_guard' => 1,
        ]);
    }

    public function test_approval_endpoints_require_authentication(): void
    {
        [$owner, $workspace, $requester, $type, $workflow] = $this->setupDefinition();
        $this->roleStep($workflow, 1, WorkspaceRole::Owner);
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $approval = $submission->approvals()->firstOrFail();
        $this->app['auth']->forgetGuards();
        $this->withToken('invalid');

        foreach ([
            $this->getJson($this->inboxUrl($workspace)),
            $this->getJson($this->approvalUrl($workspace, $approval)),
            $this->postJson($this->approveUrl($workspace, $approval)),
            $this->postJson($this->rejectUrl($workspace, $approval)),
        ] as $response) {
            $response->assertUnauthorized();
        }
    }

    /** @return array{User, Workspace, User, RequestType, ?Workflow} */
    private function setupDefinition(bool $createWorkflow = true): array
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
        $workflow = $createWorkflow ? $this->activeWorkflow($type, $owner) : null;

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

    private function activeWorkflow(RequestType $type, User $creator, int $version = 1): Workflow
    {
        return Workflow::factory()->create([
            'workspace_id' => $type->workspace_id,
            'request_type_id' => $type,
            'created_by' => $creator,
            'version' => $version,
            'status' => WorkflowStatus::Active,
            'draft_guard' => null,
            'active_guard' => 1,
            'published_at' => now(),
        ]);
    }

    private function draftWorkflow(RequestType $type, User $creator, int $version): Workflow
    {
        return Workflow::factory()->create([
            'workspace_id' => $type->workspace_id,
            'request_type_id' => $type,
            'created_by' => $creator,
            'version' => $version,
            'status' => WorkflowStatus::Draft,
            'draft_guard' => 1,
            'active_guard' => null,
            'published_at' => null,
        ]);
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

    private function userStep(Workflow $workflow, int $position, User $user, string $name = 'Approval'): WorkflowStep
    {
        return WorkflowStep::factory()->create([
            'workflow_id' => $workflow,
            'position' => $position,
            'name' => $name,
            'approver_type' => 'user',
            'approver_role' => null,
            'approver_user_id' => $user,
        ]);
    }

    private function field(RequestType $type, string $key, RequestFieldType $fieldType): RequestTypeField
    {
        return RequestTypeField::factory()->create([
            'request_type_id' => $type,
            'key' => $key,
            'type' => $fieldType,
        ]);
    }

    private function condition(WorkflowStep $step, RequestTypeField $field, string $operator, mixed $value): void
    {
        $step->conditions()->create([
            'request_type_field_id' => $field->id,
            'operator' => $operator,
            'value' => $value,
            'position' => 1,
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

    private function assertDraftRollback(RequestSubmission $submission): void
    {
        $submission->refresh();
        $this->assertSame(RequestStatus::Draft, $submission->status);
        $this->assertNull($submission->workflow_id);
        $this->assertNull($submission->definition_snapshot);
        $this->assertNull($submission->submitted_at);
        $this->assertNull($submission->resolved_at);
        $this->assertSame(0, $submission->approvals()->count());
    }

    private function authenticate(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    private function requestsUrl(Workspace $workspace): string
    {
        return "/api/v1/workspaces/{$workspace->id}/requests";
    }

    private function requestUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return $this->requestsUrl($workspace)."/{$submission->id}";
    }

    private function submitUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return $this->requestUrl($workspace, $submission).'/submit';
    }

    private function cancelUrl(Workspace $workspace, RequestSubmission $submission): string
    {
        return $this->requestUrl($workspace, $submission).'/cancel';
    }

    private function inboxUrl(Workspace $workspace): string
    {
        return "/api/v1/workspaces/{$workspace->id}/approvals";
    }

    private function approvalUrl(Workspace $workspace, RequestApproval $approval): string
    {
        return $this->inboxUrl($workspace)."/{$approval->id}";
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

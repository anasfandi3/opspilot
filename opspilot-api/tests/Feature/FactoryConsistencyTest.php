<?php

namespace Tests\Feature;

use App\Enums\RequestApprovalStatus;
use App\Enums\RequestStatus;
use App\Enums\WorkspacePermission;
use App\Models\RequestActivity;
use App\Models\RequestApproval;
use App\Models\RequestApprovalAssignee;
use App\Models\RequestAttachment;
use App\Models\RequestComment;
use App\Models\RequestSubmission;
use App\Models\Workflow;
use App\Models\WorkflowStepCondition;
use App\Models\WorkspaceInvitation;
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_factories_create_tenant_coherent_domain_graphs(): void
    {
        $workflow = Workflow::factory()->create();
        $this->assertSame($workflow->requestType->workspace_id, $workflow->workspace_id);
        $this->assertSame($workflow->requestType->created_by, $workflow->created_by);

        $submission = RequestSubmission::factory()->create();
        $this->assertSame($submission->requestType->workspace_id, $submission->workspace_id);
        $this->assertSame($submission->requestType->created_by, $submission->created_by);
        $this->assertSame(RequestStatus::Draft, $submission->status);
        $this->assertNull($submission->workflow_id);
        $this->assertNull($submission->submitted_at);
        $this->assertSame(0, $submission->approvals()->count());

        $approval = RequestApproval::factory()->create();
        $approvalSubmission = $approval->requestSubmission;
        $approvalWorkflow = $approval->workflowStep->workflow;
        $this->assertSame(RequestStatus::Submitted, $approvalSubmission->status);
        $this->assertSame($approvalSubmission->workspace_id, $approval->workspace_id);
        $this->assertSame($approvalSubmission->workflow_id, $approvalWorkflow->id);
        $this->assertSame($approvalSubmission->request_type_id, $approvalWorkflow->request_type_id);
        $this->assertNotNull($approvalSubmission->submitted_at);
        $this->assertNotNull($approvalSubmission->definition_snapshot);

        $assignee = RequestApprovalAssignee::factory()->create();
        $this->assertSame(RequestApprovalStatus::Pending, $assignee->approval->status);
        $this->assertSame($assignee->approval->workspace_id, $assignee->approval->requestSubmission->workspace_id);
        $this->assertTrue($assignee->approval->workspace->memberships()->where('user_id', $assignee->user_id)->exists());
        $this->assertTrue(app(WorkspacePermissions::class)->allows(
            $assignee->user,
            $assignee->approval->workspace,
            WorkspacePermission::ApprovalsAct,
        ));

        foreach ([RequestComment::factory()->create(), RequestAttachment::factory()->create(), RequestActivity::factory()->create()] as $record) {
            $this->assertSame($record->requestSubmission->workspace_id, $record->workspace_id);
        }

        $condition = WorkflowStepCondition::factory()->create();
        $this->assertSame(
            $condition->step->workflow->request_type_id,
            $condition->requestTypeField->request_type_id,
        );

        $invitation = WorkspaceInvitation::factory()->create();
        $this->assertSame($invitation->workspace->owner_id, $invitation->invited_by);
    }
}

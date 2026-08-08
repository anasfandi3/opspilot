<?php

namespace Tests\Feature\Api\V1;

use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestFieldType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepCondition;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_manage_workflows_but_requester_cannot(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $requestType->update(['is_active' => false]);
        $this->authenticate($owner);
        $this->postJson($this->workflowsUrl($workspace, $requestType), ['name' => 'Approval'])
            ->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.status', 'draft');

        $admin = User::factory()->create();
        $this->member($workspace, $admin, WorkspaceRole::Admin);
        $this->authenticate($admin);
        $this->patchJson($this->workflowUrl($workspace, $requestType, Workflow::query()->sole()), ['name' => 'Admin edit'])->assertOk();

        $requester = User::factory()->create();
        $this->member($workspace, $requester, WorkspaceRole::Requester);
        $this->authenticate($requester);
        $this->postJson($this->workflowsUrl($workspace, $requestType), ['name' => 'Denied'])->assertForbidden();
    }

    public function test_view_permission_lists_versions_newest_first_without_granting_management(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $active = $this->workflow($workspace, $requestType, $owner, 1, WorkflowStatus::Active);
        $archived = $this->workflow($workspace, $requestType, $owner, 2, WorkflowStatus::Archived);
        $viewer = User::factory()->create();
        $this->member($workspace, $viewer, WorkspaceRole::Requester);
        app(WorkspacePermissions::class)->within($workspace, fn () => $viewer->givePermissionTo(WorkspacePermission::WorkflowsView->value));
        $this->authenticate($viewer);

        $this->getJson($this->workflowsUrl($workspace, $requestType))->assertOk()
            ->assertJsonPath('data.0.id', $archived->id)->assertJsonPath('data.1.id', $active->id);
        $this->getJson($this->workflowUrl($workspace, $requestType, $active))->assertOk();
        $this->patchJson($this->workflowUrl($workspace, $requestType, $active), ['name' => 'Denied'])->assertForbidden();
    }

    public function test_initial_workflow_is_unique_and_database_guards_one_draft_and_version(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $this->authenticate($owner);
        $this->postJson($this->workflowsUrl($workspace, $requestType), ['name' => 'First'])->assertCreated();
        $this->postJson($this->workflowsUrl($workspace, $requestType), ['name' => 'Second'])
            ->assertUnprocessable()->assertJsonValidationErrors('workflow');

        try {
            $this->workflow($workspace, $requestType, $owner, 2, WorkflowStatus::Draft);
            $this->fail('The duplicate draft guard was not enforced.');
        } catch (QueryException) {
            $this->assertSame(1, $requestType->workflows()->count());
        }
    }

    public function test_nested_bindings_enforce_workspace_and_request_type_isolation(): void
    {
        [$ownerA, $workspaceA, $typeA] = $this->setupType();
        [$ownerB, $workspaceB, $typeB] = $this->setupType();
        $workflowB = $this->workflow($workspaceB, $typeB, $ownerB);
        $this->authenticate($ownerA);

        $this->getJson($this->workflowsUrl($workspaceA, $typeB))->assertNotFound();
        $this->getJson($this->workflowUrl($workspaceA, $typeA, $workflowB))->assertNotFound();
    }

    public function test_draft_metadata_is_editable_and_draft_is_deletable(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $this->authenticate($owner);

        $this->patchJson($this->workflowUrl($workspace, $requestType, $draft), ['name' => 'Changed', 'description' => 'New'])
            ->assertOk()->assertJsonPath('data.name', 'Changed');
        $this->deleteJson($this->workflowUrl($workspace, $requestType, $draft))->assertOk();
        $this->assertModelMissing($draft);

        $archived = $this->workflow($workspace, $requestType, $owner, 2, WorkflowStatus::Archived);
        $this->deleteJson($this->workflowUrl($workspace, $requestType, $archived))->assertUnprocessable();
    }

    public function test_valid_role_approvers_append_positions_and_disallowed_roles_are_rejected(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $this->authenticate($owner);
        $url = $this->stepsUrl($workspace, $requestType, $draft);

        foreach (['owner', 'admin', 'approver'] as $index => $role) {
            $this->postJson($url, $this->roleStep("Step {$index}", $role))
                ->assertCreated()->assertJsonPath('data.position', $index + 1);
        }
        foreach (['requester', 'auditor'] as $role) {
            $this->postJson($url, $this->roleStep('Invalid', $role))->assertUnprocessable();
        }
    }

    public function test_specific_approver_must_be_member_with_scoped_approval_permission(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $approver = User::factory()->create();
        $requester = User::factory()->create();
        $foreign = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $this->member($workspace, $requester, WorkspaceRole::Requester);
        $this->authenticate($owner);
        $url = $this->stepsUrl($workspace, $requestType, $draft);

        $this->postJson($url, $this->userStep('Valid', $approver))->assertCreated();
        $this->postJson($url, $this->userStep('No permission', $requester))->assertUnprocessable();
        $this->postJson($url, $this->userStep('Foreign', $foreign))->assertUnprocessable();
    }

    public function test_conditions_require_fields_from_the_workflow_request_type(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $otherType = $this->requestType($workspace, $owner, 'Other');
        $foreignField = $this->field($otherType, 'foreign', RequestFieldType::Number);
        $draft = $this->workflow($workspace, $requestType, $owner);
        $this->authenticate($owner);

        $this->postJson($this->stepsUrl($workspace, $requestType, $draft), $this->conditionStep($foreignField, 'equals', 1))
            ->assertUnprocessable()->assertJsonValidationErrors('conditions.0.field_id');
    }

    public function test_numeric_text_boolean_and_operator_validation_is_type_aware(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $number = $this->field($requestType, 'amount', RequestFieldType::Number);
        $text = $this->field($requestType, 'summary', RequestFieldType::Text);
        $boolean = $this->field($requestType, 'urgent', RequestFieldType::Boolean);
        $this->authenticate($owner);
        $url = $this->stepsUrl($workspace, $requestType, $draft);

        $this->postJson($url, $this->conditionStep($number, 'greater_than_or_equal', 100))->assertCreated();
        $this->postJson($url, $this->conditionStep($number, 'contains', 100))->assertUnprocessable();
        $this->postJson($url, $this->conditionStep($number, 'equals', '100'))->assertUnprocessable();
        $this->postJson($url, $this->conditionStep($text, 'equals', 'internal'))->assertCreated();
        $this->postJson($url, $this->conditionStep($text, 'greater_than', 'a'))->assertUnprocessable();
        $this->postJson($url, $this->conditionStep($boolean, 'equals', true))->assertCreated();
        $this->postJson($url, $this->conditionStep($boolean, 'equals', 1))->assertUnprocessable();
    }

    public function test_select_multiselect_and_set_condition_values_are_validated(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $config = ['options' => [['value' => 'low', 'label' => 'Low'], ['value' => 'high', 'label' => 'High']]];
        $select = $this->field($requestType, 'priority', RequestFieldType::Select, $config);
        $multi = $this->field($requestType, 'departments', RequestFieldType::Multiselect, $config);
        $this->authenticate($owner);
        $url = $this->stepsUrl($workspace, $requestType, $draft);

        $this->postJson($url, $this->conditionStep($select, 'equals', 'high'))->assertCreated();
        $this->postJson($url, $this->conditionStep($select, 'equals', 'missing'))->assertUnprocessable();
        $this->postJson($url, $this->conditionStep($select, 'in', ['low', 'high']))->assertCreated();
        $this->postJson($url, $this->conditionStep($select, 'not_in', []))->assertUnprocessable();
        $this->postJson($url, $this->conditionStep($select, 'in', ['low', 'low']))->assertUnprocessable();
        $this->postJson($url, $this->conditionStep($multi, 'contains', 'high'))->assertCreated();
        $this->postJson($url, $this->conditionStep($multi, 'equals', 'high'))->assertUnprocessable();
    }

    public function test_date_and_datetime_conditions_require_valid_values(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $date = $this->field($requestType, 'due_date', RequestFieldType::Date);
        $datetime = $this->field($requestType, 'starts_at', RequestFieldType::Datetime);
        $this->authenticate($owner);
        $url = $this->stepsUrl($workspace, $requestType, $draft);

        $this->postJson($url, $this->conditionStep($date, 'less_than', '2027-01-01'))->assertCreated();
        $this->postJson($url, $this->conditionStep($date, 'equals', '2027-13-99'))->assertUnprocessable();
        $this->postJson($url, $this->conditionStep($datetime, 'greater_than', '2027-01-01T12:00:00+00:00'))->assertCreated();
        $this->postJson($url, $this->conditionStep($datetime, 'equals', 'not-a-date'))->assertUnprocessable();
    }

    public function test_empty_conditions_always_apply_and_step_update_synchronizes_ordered_conditions(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $first = $this->field($requestType, 'first', RequestFieldType::Text);
        $second = $this->field($requestType, 'second', RequestFieldType::Text);
        $this->authenticate($owner);
        $created = $this->postJson($this->stepsUrl($workspace, $requestType, $draft), $this->roleStep('Always'))->assertCreated();
        $this->assertSame([], $created->json('data.conditions'));
        $step = WorkflowStep::query()->findOrFail($created->json('data.id'));

        $payload = [...$this->roleStep('Conditional'), 'condition_logic' => 'any', 'conditions' => [
            ['field_id' => $second->id, 'operator' => 'equals', 'value' => 'b'],
            ['field_id' => $first->id, 'operator' => 'not_equals', 'value' => 'a'],
        ]];
        $this->patchJson($this->stepUrl($workspace, $requestType, $draft, $step), $payload)
            ->assertOk()->assertJsonPath('data.conditions.0.field.id', $second->id)
            ->assertJsonPath('data.conditions.1.position', 2)->assertJsonPath('data.condition_logic', 'any');
        $this->assertSame(2, $step->conditions()->count());

        $this->patchJson($this->stepUrl($workspace, $requestType, $draft, $step), $this->roleStep('Always again'))->assertOk();
        $this->assertSame(0, $step->conditions()->count());
    }

    public function test_partial_step_updates_preserve_approver_configuration_and_omitted_conditions(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $field = $this->field($requestType, 'amount', RequestFieldType::Number);
        $step = $this->step($draft, 1);
        $condition = $step->conditions()->create([
            'request_type_field_id' => $field->id,
            'operator' => 'greater_than',
            'value' => 100,
            'position' => 1,
        ]);
        $this->authenticate($owner);
        $url = $this->stepUrl($workspace, $requestType, $draft, $step);

        $this->patchJson($url, ['name' => 'Renamed'])->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.approver_type', 'role')
            ->assertJsonPath('data.approver_role', 'approver')
            ->assertJsonPath('data.conditions.0.id', $condition->id);
        $this->patchJson($url, ['condition_logic' => 'any'])->assertOk()
            ->assertJsonPath('data.condition_logic', 'any')
            ->assertJsonPath('data.conditions.0.id', $condition->id);

        $step->refresh();
        $this->assertSame('approver', $step->approver_role->value);
        $this->assertNull($step->approver_user_id);
        $this->assertSame([$condition->id], $step->conditions()->pluck('id')->all());
    }

    public function test_explicit_empty_conditions_clear_the_step_conditions(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $field = $this->field($requestType, 'summary', RequestFieldType::Text);
        $step = $this->step($draft, 1);
        $step->conditions()->create([
            'request_type_field_id' => $field->id,
            'operator' => 'equals',
            'value' => 'urgent',
            'position' => 1,
        ]);
        $this->authenticate($owner);

        $this->patchJson($this->stepUrl($workspace, $requestType, $draft, $step), ['conditions' => []])
            ->assertOk()->assertJsonPath('data.conditions', []);
        $this->assertSame(0, $step->conditions()->count());
    }

    public function test_switching_approver_types_clears_the_inactive_approver_column(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $approver = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $roleStep = $this->step($draft, 1);
        $userStep = WorkflowStep::factory()->create([
            'workflow_id' => $draft,
            'position' => 2,
            'approver_type' => 'user',
            'approver_role' => null,
            'approver_user_id' => $approver,
        ]);
        $this->authenticate($owner);

        $this->patchJson($this->stepUrl($workspace, $requestType, $draft, $roleStep), [
            'approver_type' => 'user',
            'approver_user_id' => $approver->id,
        ])->assertOk();
        $roleStep->refresh();
        $this->assertSame('user', $roleStep->approver_type->value);
        $this->assertNull($roleStep->approver_role);
        $this->assertSame($approver->id, $roleStep->approver_user_id);

        $this->patchJson($this->stepUrl($workspace, $requestType, $draft, $userStep), [
            'approver_type' => 'role',
            'approver_role' => 'admin',
        ])->assertOk();
        $userStep->refresh();
        $this->assertSame('role', $userStep->approver_type->value);
        $this->assertSame('admin', $userStep->approver_role->value);
        $this->assertNull($userStep->approver_user_id);
    }

    public function test_invalid_partial_transition_leaves_the_step_and_conditions_unchanged(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $field = $this->field($requestType, 'amount', RequestFieldType::Number);
        $step = $this->step($draft, 1, 'Original');
        $condition = $step->conditions()->create([
            'request_type_field_id' => $field->id,
            'operator' => 'greater_than',
            'value' => 100,
            'position' => 1,
        ]);
        $this->authenticate($owner);

        $this->patchJson($this->stepUrl($workspace, $requestType, $draft, $step), [
            'name' => 'Must roll back',
            'approver_type' => 'user',
            'conditions' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('approver_user_id');

        $step->refresh();
        $this->assertSame('Original', $step->name);
        $this->assertSame('role', $step->approver_type->value);
        $this->assertSame('approver', $step->approver_role->value);
        $this->assertSame([$condition->id], $step->conditions()->pluck('id')->all());
    }

    public function test_draft_steps_update_delete_and_cross_workflow_binding_are_enforced(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $other = $this->workflow($workspace, $requestType, $owner, 2, WorkflowStatus::Archived);
        $step = $this->step($draft, 1);
        $foreign = $this->step($other, 1);
        $this->authenticate($owner);

        $this->patchJson($this->stepUrl($workspace, $requestType, $draft, $step), $this->roleStep('Updated'))->assertOk();
        $this->patchJson($this->stepUrl($workspace, $requestType, $draft, $foreign), $this->roleStep('Cross'))->assertNotFound();
        $this->deleteJson($this->stepUrl($workspace, $requestType, $draft, $step))->assertOk();
        $this->assertModelMissing($step);
    }

    public function test_full_set_step_reorder_and_invalid_sets(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $first = $this->step($draft, 1);
        $second = $this->step($draft, 2);
        $third = $this->step($draft, 3);
        $otherWorkflow = $this->workflow($workspace, $requestType, $owner, 2, WorkflowStatus::Archived);
        $foreign = $this->step($otherWorkflow, 1);
        $this->authenticate($owner);
        $url = $this->reorderUrl($workspace, $requestType, $draft);

        $this->postJson($url, ['step_ids' => [$third->id, $first->id, $second->id]])
            ->assertOk()->assertJsonPath('data.0.id', $third->id);
        $this->postJson($url, ['step_ids' => [$first->id, $first->id, $third->id]])->assertUnprocessable();
        $this->postJson($url, ['step_ids' => [$first->id, $second->id]])->assertUnprocessable();
        $this->postJson($url, ['step_ids' => [$first->id, $second->id, $foreign->id]])->assertUnprocessable();
    }

    public function test_step_reorder_rolls_back_atomically(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $first = $this->step($draft, 1, 'First');
        $second = $this->step($draft, 2, 'Second');
        WorkflowStep::updating(static fn (WorkflowStep $step) => $step->name === 'First' ? throw new RuntimeException('fail') : null);
        $this->authenticate($owner);
        $this->withoutExceptionHandling();
        try {
            $this->postJson($this->reorderUrl($workspace, $requestType, $draft), ['step_ids' => [$second->id, $first->id]]);
        } catch (RuntimeException) {
            $this->assertSame([1, 2], WorkflowStep::query()->where('workflow_id', $draft->id)->orderBy('id')->pluck('position')->all());
        } finally {
            WorkflowStep::flushEventListeners();
        }
    }

    public function test_empty_workflow_cannot_publish_and_valid_workflow_becomes_immutable(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $this->authenticate($owner);
        $this->postJson($this->publishUrl($workspace, $requestType, $draft))->assertUnprocessable();
        $step = $this->step($draft, 1);
        $this->postJson($this->publishUrl($workspace, $requestType, $draft))->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->patchJson($this->workflowUrl($workspace, $requestType, $draft), ['name' => 'No'])->assertUnprocessable();
        $this->deleteJson($this->workflowUrl($workspace, $requestType, $draft))->assertUnprocessable();
        $this->postJson($this->stepsUrl($workspace, $requestType, $draft), $this->roleStep('No'))->assertUnprocessable();
        $this->patchJson($this->stepUrl($workspace, $requestType, $draft, $step), $this->roleStep('No'))->assertUnprocessable();
    }

    public function test_clone_deep_copies_definition_and_new_publish_archives_previous_active(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $active = $this->workflow($workspace, $requestType, $owner, 1, WorkflowStatus::Active);
        $field = $this->field($requestType, 'amount', RequestFieldType::Number);
        $step = $this->step($active, 1);
        $step->conditions()->create(['request_type_field_id' => $field->id, 'operator' => 'greater_than', 'value' => 100, 'position' => 1]);
        $this->authenticate($owner);

        $cloneResponse = $this->postJson($this->cloneUrl($workspace, $requestType, $active))->assertCreated()
            ->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.steps.0.conditions.0.value', 100);
        $clone = Workflow::query()->findOrFail($cloneResponse->json('data.id'));
        $this->assertNotSame($step->id, $clone->steps()->first()->id);
        $this->assertNotSame($step->conditions()->first()->id, $clone->steps()->first()->conditions()->first()->id);
        $this->postJson($this->cloneUrl($workspace, $requestType, $active))->assertUnprocessable();

        $this->postJson($this->publishUrl($workspace, $requestType, $clone))->assertOk();
        $this->assertSame(WorkflowStatus::Archived, $active->fresh()->status);
        $this->assertSame(WorkflowStatus::Active, $clone->fresh()->status);
        $this->assertSame(1, $requestType->workflows()->where('status', 'active')->count());
    }

    public function test_publish_revalidates_specific_approver_membership_and_permission(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $approver = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $this->authenticate($owner);
        $this->postJson($this->stepsUrl($workspace, $requestType, $draft), $this->userStep('Approval', $approver))->assertCreated();
        app(WorkspacePermissions::class)->assign($approver, $workspace, WorkspaceRole::Requester);

        $this->postJson($this->publishUrl($workspace, $requestType, $draft))->assertUnprocessable();
        $this->assertSame(WorkflowStatus::Draft, $draft->fresh()->status);

        app(WorkspacePermissions::class)->assign($approver, $workspace, WorkspaceRole::Approver);
        WorkspaceMembership::query()->where('workspace_id', $workspace->id)->where('user_id', $approver->id)->delete();
        $this->postJson($this->publishUrl($workspace, $requestType, $draft))->assertUnprocessable();
    }

    public function test_publish_revalidates_conditions_against_current_field_definition(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $field = $this->field($requestType, 'priority', RequestFieldType::Select, [
            'options' => [['value' => 'high', 'label' => 'High']],
        ]);
        $this->authenticate($owner);
        $this->postJson($this->stepsUrl($workspace, $requestType, $draft), $this->conditionStep($field, 'equals', 'high'))->assertCreated();
        $field->forceFill(['config' => ['options' => [['value' => 'low', 'label' => 'Low']]]])->save();

        $this->postJson($this->publishUrl($workspace, $requestType, $draft))->assertUnprocessable();
    }

    public function test_referenced_field_delete_and_invalidating_config_update_are_blocked(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $draft = $this->workflow($workspace, $requestType, $owner);
        $field = $this->field($requestType, 'priority', RequestFieldType::Select, [
            'options' => [['value' => 'low', 'label' => 'Low'], ['value' => 'high', 'label' => 'High']],
        ]);
        $unrelated = $this->field($requestType, 'notes', RequestFieldType::Text);
        $this->authenticate($owner);
        $this->postJson($this->stepsUrl($workspace, $requestType, $draft), $this->conditionStep($field, 'equals', 'high'))->assertCreated();

        $this->deleteJson($this->fieldUrl($workspace, $requestType, $field))->assertUnprocessable();
        $this->patchJson($this->fieldUrl($workspace, $requestType, $field), ['config' => [
            'options' => [['value' => 'low', 'label' => 'Low']],
        ]])->assertUnprocessable();
        $this->patchJson($this->fieldUrl($workspace, $requestType, $field), ['config' => [
            'options' => [['value' => 'low', 'label' => 'Low'], ['value' => 'high', 'label' => 'High'], ['value' => 'urgent', 'label' => 'Urgent']],
        ]])->assertOk();
        $this->patchJson($this->fieldUrl($workspace, $requestType, $unrelated), ['label' => 'Updated'])->assertOk();
        $this->deleteJson($this->fieldUrl($workspace, $requestType, $unrelated))->assertOk();
    }

    public function test_deleting_request_type_removes_workflow_configuration_tree_in_dependency_order(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $workflow = $this->workflow($workspace, $requestType, $owner);
        $field = $this->field($requestType, 'amount', RequestFieldType::Number);
        $step = $this->step($workflow, 1);
        $condition = $step->conditions()->create([
            'request_type_field_id' => $field->id,
            'operator' => 'greater_than',
            'value' => 100,
            'position' => 1,
        ]);
        $this->authenticate($owner);

        $this->deleteJson($this->requestTypeUrl($workspace, $requestType))->assertOk();

        $this->assertModelMissing($condition);
        $this->assertModelMissing($step);
        $this->assertModelMissing($workflow);
        $this->assertModelMissing($field);
        $this->assertModelMissing($requestType);
        $this->assertSame(0, WorkflowStepCondition::query()->count());
    }

    public function test_workflow_endpoints_require_authentication(): void
    {
        [$owner, $workspace, $requestType] = $this->setupType();
        $workflow = $this->workflow($workspace, $requestType, $owner);
        $step = $this->step($workflow, 1);
        foreach ([
            $this->getJson($this->workflowsUrl($workspace, $requestType)),
            $this->postJson($this->workflowsUrl($workspace, $requestType), ['name' => 'No']),
            $this->getJson($this->workflowUrl($workspace, $requestType, $workflow)),
            $this->patchJson($this->workflowUrl($workspace, $requestType, $workflow), ['name' => 'No']),
            $this->deleteJson($this->workflowUrl($workspace, $requestType, $workflow)),
            $this->postJson($this->publishUrl($workspace, $requestType, $workflow)),
            $this->postJson($this->cloneUrl($workspace, $requestType, $workflow)),
            $this->postJson($this->stepsUrl($workspace, $requestType, $workflow), $this->roleStep('No')),
            $this->patchJson($this->stepUrl($workspace, $requestType, $workflow, $step), $this->roleStep('No')),
            $this->deleteJson($this->stepUrl($workspace, $requestType, $workflow, $step)),
            $this->postJson($this->reorderUrl($workspace, $requestType, $workflow), ['step_ids' => [$step->id]]),
        ] as $response) {
            $response->assertUnauthorized();
        }
    }

    /** @return array{User, Workspace, RequestType} */
    private function setupType(): array
    {
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);

        return [$owner, $workspace, $this->requestType($workspace, $owner)];
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

    private function requestType(Workspace $workspace, User $creator, string $name = 'Purchase Request'): RequestType
    {
        return RequestType::factory()->create(['workspace_id' => $workspace, 'created_by' => $creator, 'name' => $name]);
    }

    private function workflow(Workspace $workspace, RequestType $requestType, User $creator, int $version = 1, WorkflowStatus $status = WorkflowStatus::Draft): Workflow
    {
        return Workflow::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $requestType,
            'created_by' => $creator,
            'version' => $version,
            'status' => $status,
            'draft_guard' => $status === WorkflowStatus::Draft ? 1 : null,
            'active_guard' => $status === WorkflowStatus::Active ? 1 : null,
            'published_at' => $status === WorkflowStatus::Draft ? null : now(),
        ]);
    }

    private function step(Workflow $workflow, int $position, string $name = 'Approval'): WorkflowStep
    {
        return WorkflowStep::factory()->create(['workflow_id' => $workflow, 'position' => $position, 'name' => $name]);
    }

    private function field(RequestType $requestType, string $key, RequestFieldType $type, ?array $config = null): RequestTypeField
    {
        return RequestTypeField::factory()->create(['request_type_id' => $requestType, 'key' => $key, 'type' => $type, 'config' => $config]);
    }

    /** @return array<string, mixed> */
    private function roleStep(string $name, string $role = 'approver'): array
    {
        return ['name' => $name, 'approver_type' => 'role', 'approver_role' => $role, 'approver_user_id' => null, 'condition_logic' => 'all', 'conditions' => []];
    }

    /** @return array<string, mixed> */
    private function userStep(string $name, User $user): array
    {
        return ['name' => $name, 'approver_type' => 'user', 'approver_role' => null, 'approver_user_id' => $user->id, 'condition_logic' => 'all', 'conditions' => []];
    }

    /** @return array<string, mixed> */
    private function conditionStep(RequestTypeField $field, string $operator, mixed $value): array
    {
        return [...$this->roleStep('Conditional'), 'conditions' => [['field_id' => $field->id, 'operator' => $operator, 'value' => $value]]];
    }

    private function authenticate(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    private function workflowsUrl(Workspace $workspace, RequestType $requestType): string
    {
        return "/api/v1/workspaces/{$workspace->id}/request-types/{$requestType->id}/workflows";
    }

    private function workflowUrl(Workspace $workspace, RequestType $requestType, Workflow $workflow): string
    {
        return $this->workflowsUrl($workspace, $requestType)."/{$workflow->id}";
    }

    private function stepsUrl(Workspace $workspace, RequestType $requestType, Workflow $workflow): string
    {
        return $this->workflowUrl($workspace, $requestType, $workflow).'/steps';
    }

    private function stepUrl(Workspace $workspace, RequestType $requestType, Workflow $workflow, WorkflowStep $step): string
    {
        return $this->stepsUrl($workspace, $requestType, $workflow)."/{$step->id}";
    }

    private function reorderUrl(Workspace $workspace, RequestType $requestType, Workflow $workflow): string
    {
        return $this->stepsUrl($workspace, $requestType, $workflow).'/reorder';
    }

    private function publishUrl(Workspace $workspace, RequestType $requestType, Workflow $workflow): string
    {
        return $this->workflowUrl($workspace, $requestType, $workflow).'/publish';
    }

    private function cloneUrl(Workspace $workspace, RequestType $requestType, Workflow $workflow): string
    {
        return $this->workflowUrl($workspace, $requestType, $workflow).'/clone';
    }

    private function fieldUrl(Workspace $workspace, RequestType $requestType, RequestTypeField $field): string
    {
        return "/api/v1/workspaces/{$workspace->id}/request-types/{$requestType->id}/fields/{$field->id}";
    }

    private function requestTypeUrl(Workspace $workspace, RequestType $requestType): string
    {
        return "/api/v1/workspaces/{$workspace->id}/request-types/{$requestType->id}";
    }
}

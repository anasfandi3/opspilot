<?php

namespace Tests\Feature\Api\V1;

use App\Actions\PublishWorkflow;
use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestFieldType;
use App\Enums\RequestStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_returns_only_active_types_with_active_workflows_and_ordered_fields(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $available = $this->requestType($workspace, $owner, 'Available');
        $this->activeWorkflow($available, $owner);
        $second = $this->field($available, 'second', RequestFieldType::Text, position: 2);
        $first = $this->field($available, 'first', RequestFieldType::Text, position: 1);
        $inactive = $this->requestType($workspace, $owner, 'Inactive', false);
        $this->activeWorkflow($inactive, $owner);
        $this->requestType($workspace, $owner, 'No workflow');
        $draftOnly = $this->requestType($workspace, $owner, 'Draft only');
        $this->draftWorkflow($draftOnly, $owner);
        $this->authenticate($requester);

        $this->getJson($this->catalogUrl($workspace))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id)
            ->assertJsonPath('data.0.fields.0.id', $first->id)
            ->assertJsonPath('data.0.fields.1.id', $second->id)
            ->assertJsonMissing(['is_active' => true])
            ->assertJsonMissingPath('data.0.workflow');
    }

    public function test_catalog_enforces_workspace_permission_and_authentication(): void
    {
        [$ownerA, $workspaceA, $requester] = $this->setupWorkspace();
        [$ownerB, $workspaceB] = $this->setupWorkspace();
        $typeB = $this->requestType($workspaceB, $ownerB, 'Foreign');
        $this->activeWorkflow($typeB, $ownerB);
        $this->authenticate($requester);
        $this->getJson($this->catalogUrl($workspaceA))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->catalogUrl($workspaceB))->assertForbidden();

        $approver = User::factory()->create();
        $this->member($workspaceA, $approver, WorkspaceRole::Approver);
        $this->authenticate($approver);
        $this->getJson($this->catalogUrl($workspaceA))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken('invalid');
        $this->getJson($this->catalogUrl($workspaceA))->assertUnauthorized();
    }

    public function test_eligible_user_creates_empty_and_valid_partial_drafts(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $this->activeWorkflow($type, $owner);
        $this->field($type, 'quantity', RequestFieldType::Number, true, ['min' => 1]);
        $this->authenticate($requester);

        $empty = $this->postJson($this->createUrl($workspace, $type), [])->assertCreated()
            ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.payload', [])
            ->assertJsonPath('data.workflow', null);
        $partial = $this->postJson($this->createUrl($workspace, $type), ['payload' => ['quantity' => 2]])
            ->assertCreated()->assertJsonPath('data.payload.quantity', 2);

        $this->assertNull(RequestSubmission::query()->findOrFail($empty->json('data.id'))->workflow_id);
        $this->assertSame($requester->id, RequestSubmission::query()->findOrFail($partial->json('data.id'))->created_by);
    }

    public function test_creation_rejects_unavailable_and_cross_workspace_request_types(): void
    {
        [$ownerA, $workspaceA, $requester] = $this->setupWorkspace();
        [$ownerB, $workspaceB] = $this->setupWorkspace();
        $inactive = $this->requestType($workspaceA, $ownerA, 'Inactive', false);
        $this->activeWorkflow($inactive, $ownerA);
        $withoutWorkflow = $this->requestType($workspaceA, $ownerA, 'Missing workflow');
        $foreign = $this->requestType($workspaceB, $ownerB, 'Foreign');
        $this->activeWorkflow($foreign, $ownerB);
        $this->authenticate($requester);

        $this->postJson($this->createUrl($workspaceA, $inactive), [])->assertUnprocessable();
        $this->postJson($this->createUrl($workspaceA, $withoutWorkflow), [])->assertUnprocessable();
        $this->postJson($this->createUrl($workspaceA, $foreign), [])->assertNotFound();
    }

    public function test_creation_rejects_protected_attributes_unknown_keys_and_invalid_values(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $this->activeWorkflow($type, $owner);
        $this->field($type, 'quantity', RequestFieldType::Number);
        $this->authenticate($requester);

        $this->postJson($this->createUrl($workspace, $type), [
            'workspace_id' => 999,
            'request_type_id' => 999,
            'workflow_id' => 999,
            'created_by' => $owner->id,
            'status' => 'approved',
            'definition_snapshot' => [],
            'submitted_at' => now(),
            'cancelled_at' => now(),
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'workspace_id', 'request_type_id', 'workflow_id', 'created_by', 'status',
            'definition_snapshot', 'submitted_at', 'cancelled_at',
        ]);
        $this->postJson($this->createUrl($workspace, $type), ['payload' => ['unknown' => 'value']])
            ->assertUnprocessable()->assertJsonValidationErrors('payload.unknown');
        $this->postJson($this->createUrl($workspace, $type), ['payload' => ['quantity' => '5']])
            ->assertUnprocessable()->assertJsonValidationErrors('payload.quantity');
    }

    public function test_dynamic_validator_accepts_valid_values_for_every_field_type(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $this->activeWorkflow($type, $owner);
        $this->allFieldTypes($type);
        $this->authenticate($requester);

        $payload = [
            'text' => 'hello',
            'textarea' => 'details',
            'number' => 5,
            'decimal' => 5.25,
            'boolean' => false,
            'date' => '2026-08-08',
            'datetime' => '2026-08-08T12:30:00+00:00',
            'select' => 'high',
            'multiselect' => ['low', 'high'],
            'email' => 'user@example.com',
            'url' => 'https://example.com/path',
        ];

        $this->postJson($this->createUrl($workspace, $type), ['payload' => $payload])
            ->assertCreated()->assertJsonPath('data.payload', $payload);
    }

    public function test_dynamic_validator_rejects_invalid_values_for_every_field_type(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $this->activeWorkflow($type, $owner);
        $this->allFieldTypes($type);
        $this->authenticate($requester);

        $invalid = [
            'text' => 'x',
            'textarea' => 'this is too long',
            'number' => 1.5,
            'decimal' => '5.25',
            'boolean' => 1,
            'date' => '2026-02-30',
            'datetime' => '2026-08-08 12:30:00',
            'select' => 'missing',
            'multiselect' => ['high', 'high'],
            'email' => 'invalid',
            'url' => 'not-a-url',
        ];

        foreach ($invalid as $key => $value) {
            $this->postJson($this->createUrl($workspace, $type), ['payload' => [$key => $value]])
                ->assertUnprocessable()->assertJsonValidationErrors("payload.{$key}");
        }
        $this->postJson($this->createUrl($workspace, $type), ['payload' => ['multiselect' => ['missing']]])
            ->assertUnprocessable()->assertJsonValidationErrors('payload.multiselect');
        $this->postJson($this->createUrl($workspace, $type), ['payload' => ['number' => '5']])
            ->assertUnprocessable()->assertJsonValidationErrors('payload.number');
        foreach ([
            'text' => 'this is too long',
            'textarea' => 'x',
            'number' => -1,
            'decimal' => 11.5,
            'email' => str_repeat('a', 45).'@example.com',
            'url' => 'https://example.com/'.str_repeat('a', 100),
        ] as $key => $value) {
            $this->postJson($this->createUrl($workspace, $type), ['payload' => [$key => $value]])
                ->assertUnprocessable()->assertJsonValidationErrors("payload.{$key}");
        }
    }

    public function test_submission_required_semantics_accept_falsy_values_and_reject_empty_required_values(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $this->activeWorkflow($type, $owner);
        $this->field($type, 'enabled', RequestFieldType::Boolean, true);
        $this->field($type, 'quantity', RequestFieldType::Number, true, ['min' => 0]);
        $this->field($type, 'amount', RequestFieldType::Decimal, true, ['min' => 0]);
        $this->field($type, 'title', RequestFieldType::Text, true, ['min_length' => 0]);
        $this->field($type, 'choices', RequestFieldType::Multiselect, true, $this->fieldOptions());
        $this->authenticate($requester);

        $valid = $this->submission($workspace, $type, $requester, payload: [
            'enabled' => false, 'quantity' => 0, 'amount' => 0.0, 'title' => 'Valid', 'choices' => ['low'],
        ]);
        $this->postJson($this->submitUrl($workspace, $valid))->assertOk()->assertJsonPath('data.status', 'submitted');

        $emptyText = $this->submission($workspace, $type, $requester, payload: [
            'enabled' => false, 'quantity' => 0, 'amount' => 0.0, 'title' => '', 'choices' => ['low'],
        ]);
        $this->postJson($this->submitUrl($workspace, $emptyText))->assertUnprocessable()
            ->assertJsonValidationErrors('payload.title');
        $emptyMulti = $this->submission($workspace, $type, $requester, payload: [
            'enabled' => false, 'quantity' => 0, 'amount' => 0.0, 'title' => 'Valid', 'choices' => [],
        ]);
        $this->postJson($this->submitUrl($workspace, $emptyMulti))->assertUnprocessable()
            ->assertJsonValidationErrors('payload.choices');
    }

    public function test_draft_update_replaces_payload_and_omission_preserves_it(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $this->activeWorkflow($type, $owner);
        $this->field($type, 'first', RequestFieldType::Text);
        $this->field($type, 'second', RequestFieldType::Text);
        $submission = $this->submission($workspace, $type, $requester, payload: ['first' => 'one', 'second' => 'two']);
        $this->authenticate($requester);

        $this->patchJson($this->requestUrl($workspace, $submission), [])->assertOk()
            ->assertJsonPath('data.payload.second', 'two');
        $this->patchJson($this->requestUrl($workspace, $submission), ['payload' => ['first' => 'changed']])
            ->assertOk()->assertJsonPath('data.payload', ['first' => 'changed']);
        $this->assertSame(['first' => 'changed'], $submission->fresh()->payload);
    }

    public function test_draft_update_authorization_is_creator_and_permission_specific(): void
    {
        [$owner, $workspace, $creator] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $this->activeWorkflow($type, $owner);
        $this->field($type, 'title', RequestFieldType::Text);
        $submission = $this->submission($workspace, $type, $creator, payload: ['title' => 'original']);
        $other = User::factory()->create();
        $auditor = User::factory()->create();
        $this->member($workspace, $other, WorkspaceRole::Requester);
        $this->member($workspace, $auditor, WorkspaceRole::Auditor);

        $this->authenticate($other);
        $this->patchJson($this->requestUrl($workspace, $submission), ['payload' => ['title' => 'other']])->assertForbidden();
        $this->authenticate($auditor);
        $this->patchJson($this->requestUrl($workspace, $submission), ['payload' => ['title' => 'auditor']])->assertForbidden();
        $this->assertSame(['title' => 'original'], $submission->fresh()->payload);
    }

    public function test_draft_update_rejects_protected_fields_and_rolls_back_invalid_payload(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $workflow = $this->activeWorkflow($type, $owner);
        $this->field($type, 'quantity', RequestFieldType::Number);
        $submission = $this->submission($workspace, $type, $requester, payload: ['quantity' => 1]);
        $this->authenticate($requester);

        $this->patchJson($this->requestUrl($workspace, $submission), [
            'payload' => ['quantity' => 2], 'status' => 'submitted', 'workflow_id' => $workflow->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['status', 'workflow_id']);
        $this->patchJson($this->requestUrl($workspace, $submission), ['payload' => ['quantity' => 'bad']])
            ->assertUnprocessable();
        $submission->refresh();
        $this->assertSame(['quantity' => 1], $submission->payload);
        $this->assertSame(RequestStatus::Draft, $submission->status);
        $this->assertNull($submission->workflow_id);
    }

    public function test_submitted_requests_are_immutable_through_patch(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $workflow = $this->activeWorkflow($type, $owner);
        $submission = $this->submission($workspace, $type, $requester, RequestStatus::Submitted, ['value' => 'history'], $workflow);
        $this->authenticate($requester);

        $this->patchJson($this->requestUrl($workspace, $submission), ['payload' => []])->assertForbidden();
        $this->assertSame(['value' => 'history'], $submission->fresh()->payload);
        $this->assertSame($workflow->id, $submission->fresh()->workflow_id);
    }

    public function test_listing_and_show_apply_own_and_all_scopes_with_pagination_and_filters(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $other = User::factory()->create();
        $auditor = User::factory()->create();
        $this->member($workspace, $other, WorkspaceRole::Requester);
        $this->member($workspace, $auditor, WorkspaceRole::Auditor);
        $firstType = $this->requestType($workspace, $owner, 'First');
        $secondType = $this->requestType($workspace, $owner, 'Second');
        $own = $this->submission($workspace, $firstType, $requester);
        $otherDraft = $this->submission($workspace, $firstType, $other);
        $otherCancelled = $this->submission($workspace, $secondType, $other, RequestStatus::Cancelled);

        $this->authenticate($requester);
        $this->getJson($this->requestsUrl($workspace))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)->assertJsonPath('meta.per_page', 20);
        $this->getJson($this->requestUrl($workspace, $otherDraft))->assertForbidden();

        $this->authenticate($auditor);
        $this->getJson($this->requestsUrl($workspace).'?per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson($this->requestsUrl($workspace).'?status=cancelled&request_type_id='.$secondType->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $otherCancelled->id);
        $this->getJson($this->requestUrl($workspace, $otherDraft))->assertOk();

        $approver = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $this->authenticate($approver);
        $this->getJson($this->requestsUrl($workspace))->assertForbidden();
    }

    public function test_request_routes_enforce_nested_workspace_isolation(): void
    {
        [$ownerA, $workspaceA, $requester] = $this->setupWorkspace();
        [$ownerB, $workspaceB] = $this->setupWorkspace();
        $typeA = $this->requestType($workspaceA, $ownerA);
        $typeB = $this->requestType($workspaceB, $ownerB);
        $foreignSubmission = $this->submission($workspaceB, $typeB, $ownerB);
        $this->authenticate($requester);

        $this->getJson($this->requestUrl($workspaceA, $foreignSubmission))->assertNotFound();
        $this->patchJson($this->requestUrl($workspaceA, $foreignSubmission), ['payload' => []])->assertNotFound();
        $this->postJson($this->createUrl($workspaceA, $typeB), [])->assertNotFound();
        $this->assertModelExists($typeA);
    }

    public function test_valid_submission_binds_workflow_and_creates_ordered_definition_snapshot(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner, 'Purchase Request');
        $workflow = $this->activeWorkflow($type, $owner, 1, 'Approval v1');
        $second = $this->field($type, 'quantity', RequestFieldType::Number, true, ['min' => 1], 2, 'Quantity');
        $first = $this->field($type, 'item_name', RequestFieldType::Text, true, ['max_length' => 255], 1, 'Item name');
        $submission = $this->submission($workspace, $type, $requester, payload: ['item_name' => 'Laptop', 'quantity' => 2]);
        $this->authenticate($requester);

        $response = $this->postJson($this->submitUrl($workspace, $submission))->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.workflow.id', $workflow->id)
            ->assertJsonPath('data.workflow.version', 1)
            ->assertJsonPath('data.definition_snapshot.request_type.name', 'Purchase Request')
            ->assertJsonPath('data.definition_snapshot.request_type.slug', $type->slug)
            ->assertJsonPath('data.definition_snapshot.fields.0.id', $first->id)
            ->assertJsonPath('data.definition_snapshot.fields.1.id', $second->id)
            ->assertJsonPath('data.definition_snapshot.fields.0.key', 'item_name')
            ->assertJsonPath('data.definition_snapshot.fields.0.label', 'Item name')
            ->assertJsonPath('data.definition_snapshot.fields.0.type', 'text')
            ->assertJsonPath('data.definition_snapshot.fields.0.is_required', true)
            ->assertJsonPath('data.definition_snapshot.fields.0.config.max_length', 255);

        $submission->refresh();
        $this->assertSame($workflow->id, $submission->workflow_id);
        $this->assertNotNull($submission->submitted_at);
        $this->assertNull($submission->cancelled_at);
        $this->assertSame('Laptop', $response->json('data.payload.item_name'));
    }

    public function test_failed_submission_leaves_draft_state_and_history_columns_null(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $this->activeWorkflow($type, $owner);
        $this->field($type, 'quantity', RequestFieldType::Number, true);
        $submission = $this->submission($workspace, $type, $requester, payload: []);
        $this->authenticate($requester);

        $this->postJson($this->submitUrl($workspace, $submission))->assertUnprocessable()
            ->assertJsonValidationErrors('payload.quantity');
        $submission->refresh();
        $this->assertSame(RequestStatus::Draft, $submission->status);
        $this->assertNull($submission->workflow_id);
        $this->assertNull($submission->definition_snapshot);
        $this->assertNull($submission->submitted_at);
    }

    public function test_submission_revalidates_current_type_and_active_workflow(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $inactiveType = $this->requestType($workspace, $owner, 'Inactive');
        $this->activeWorkflow($inactiveType, $owner);
        $inactiveSubmission = $this->submission($workspace, $inactiveType, $requester);
        $inactiveType->update(['is_active' => false]);
        $missingType = $this->requestType($workspace, $owner, 'Missing');
        $missingSubmission = $this->submission($workspace, $missingType, $requester);
        $this->authenticate($requester);

        $this->postJson($this->submitUrl($workspace, $inactiveSubmission))->assertUnprocessable();
        $this->postJson($this->submitUrl($workspace, $missingSubmission))->assertUnprocessable();
        $this->assertSame(RequestStatus::Draft, $inactiveSubmission->fresh()->status);
        $this->assertSame(RequestStatus::Draft, $missingSubmission->fresh()->status);
    }

    public function test_submission_is_creator_and_permission_specific_and_double_submit_is_rejected(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $this->activeWorkflow($type, $owner);
        $submission = $this->submission($workspace, $type, $requester);
        $other = User::factory()->create();
        $this->member($workspace, $other, WorkspaceRole::Requester);
        $this->authenticate($other);
        $this->postJson($this->submitUrl($workspace, $submission))->assertForbidden();

        $approver = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $approverDraft = $this->submission($workspace, $type, $approver);
        $this->authenticate($approver);
        $this->postJson($this->submitUrl($workspace, $approverDraft))->assertForbidden();

        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $this->postJson($this->submitUrl($workspace, $submission))->assertForbidden();
        $this->assertSame(1, RequestSubmission::query()->whereKey($submission->id)->where('status', 'submitted')->count());
    }

    public function test_workflow_is_chosen_at_submission_and_remains_immutable_after_later_publish(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $v1 = $this->activeWorkflow($type, $owner, 1, 'v1');
        $submission = $this->submission($workspace, $type, $requester);
        $v2 = $this->draftWorkflow($type, $owner, 2, 'v2');
        $this->step($v2);
        app(PublishWorkflow::class)->handle($v2);
        $this->authenticate($requester);

        $this->postJson($this->submitUrl($workspace, $submission))->assertOk()
            ->assertJsonPath('data.workflow.id', $v2->id);
        $v3 = $this->draftWorkflow($type, $owner, 3, 'v3');
        $this->step($v3);
        app(PublishWorkflow::class)->handle($v3);

        $this->assertSame(WorkflowStatus::Archived, $v1->fresh()->status);
        $this->assertSame(WorkflowStatus::Archived, $v2->fresh()->status);
        $this->assertSame($v2->id, $submission->fresh()->workflow_id);
    }

    public function test_snapshot_is_not_rewritten_after_request_type_and_field_changes(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner, 'Original type');
        $this->activeWorkflow($type, $owner);
        $field = $this->field($type, 'title', RequestFieldType::Text, true, ['max_length' => 20], 1, 'Original label');
        $submission = $this->submission($workspace, $type, $requester, payload: ['title' => 'Value']);
        $this->authenticate($requester);
        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $snapshot = $submission->fresh()->definition_snapshot;

        $type->update(['name' => 'Renamed type']);
        $field->update(['label' => 'Changed label', 'config' => ['max_length' => 100]]);

        $this->assertSame($snapshot, $submission->fresh()->definition_snapshot);
        $this->assertSame('Original type', $submission->fresh()->definition_snapshot['request_type']['name']);
        $this->assertSame('Original label', $submission->fresh()->definition_snapshot['fields'][0]['label']);
    }

    public function test_draft_and_submitted_requests_can_cancel_without_erasing_history(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $workflow = $this->activeWorkflow($type, $owner);
        $draft = $this->submission($workspace, $type, $requester, payload: ['value' => 'draft']);
        $submitted = $this->submission(
            $workspace,
            $type,
            $requester,
            RequestStatus::Submitted,
            ['value' => 'submitted'],
            $workflow,
            ['request_type' => ['id' => $type->id], 'fields' => []],
        );
        $this->authenticate($requester);

        $this->postJson($this->cancelUrl($workspace, $draft))->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson($this->cancelUrl($workspace, $submitted))->assertOk()->assertJsonPath('data.status', 'cancelled');
        $draft->refresh();
        $submitted->refresh();
        $this->assertNull($draft->workflow_id);
        $this->assertNull($draft->submitted_at);
        $this->assertSame(['value' => 'draft'], $draft->payload);
        $this->assertSame($workflow->id, $submitted->workflow_id);
        $this->assertNotNull($submitted->submitted_at);
        $this->assertNotNull($submitted->definition_snapshot);
        $this->assertSame(['value' => 'submitted'], $submitted->payload);
        $this->assertNotNull($submitted->cancelled_at);
    }

    public function test_terminal_requests_other_users_and_users_without_permission_cannot_cancel(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        foreach ([RequestStatus::Approved, RequestStatus::Rejected, RequestStatus::Cancelled] as $status) {
            $terminal = $this->submission($workspace, $type, $requester, $status);
            $this->authenticate($requester);
            $this->postJson($this->cancelUrl($workspace, $terminal))->assertForbidden();
        }

        $draft = $this->submission($workspace, $type, $requester);
        $other = User::factory()->create();
        $this->member($workspace, $other, WorkspaceRole::Requester);
        $this->authenticate($other);
        $this->postJson($this->cancelUrl($workspace, $draft))->assertForbidden();

        $approver = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        $approverDraft = $this->submission($workspace, $type, $approver);
        $this->authenticate($approver);
        $this->postJson($this->cancelUrl($workspace, $approverDraft))->assertForbidden();
    }

    public function test_submit_then_cancel_resolves_to_one_valid_final_state(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $workflow = $this->activeWorkflow($type, $owner);
        $submission = $this->submission($workspace, $type, $requester);
        $this->authenticate($requester);

        $this->postJson($this->submitUrl($workspace, $submission))->assertOk();
        $this->postJson($this->cancelUrl($workspace, $submission))->assertOk();
        $submission->refresh();
        $this->assertSame(RequestStatus::Cancelled, $submission->status);
        $this->assertSame($workflow->id, $submission->workflow_id);
        $this->assertNotNull($submission->submitted_at);
        $this->assertNotNull($submission->cancelled_at);
    }

    public function test_request_type_without_history_still_deletes_but_any_submission_blocks_deletion(): void
    {
        [$owner, $workspace] = $this->setupWorkspace();
        $empty = $this->requestType($workspace, $owner, 'Empty');
        $emptyWorkflow = $this->activeWorkflow($empty, $owner);
        $this->step($emptyWorkflow);
        $this->authenticate($owner);

        $this->deleteJson($this->requestTypeUrl($workspace, $empty))->assertOk();
        $this->assertModelMissing($empty);
        foreach (RequestStatus::cases() as $status) {
            $protected = $this->requestType($workspace, $owner, 'Protected '.$status->value);
            $submission = $this->submission($workspace, $protected, $owner, $status);
            $this->deleteJson($this->requestTypeUrl($workspace, $protected))->assertUnprocessable()
                ->assertJsonValidationErrors('request_type');
            $this->assertModelExists($protected);
            $this->assertModelExists($submission);
        }
    }

    public function test_request_endpoints_require_authentication(): void
    {
        [$owner, $workspace, $requester] = $this->setupWorkspace();
        $type = $this->requestType($workspace, $owner);
        $this->activeWorkflow($type, $owner);
        $submission = $this->submission($workspace, $type, $requester);

        foreach ([
            $this->getJson($this->catalogUrl($workspace)),
            $this->postJson($this->createUrl($workspace, $type), []),
            $this->getJson($this->requestsUrl($workspace)),
            $this->getJson($this->requestUrl($workspace, $submission)),
            $this->patchJson($this->requestUrl($workspace, $submission), ['payload' => []]),
            $this->postJson($this->submitUrl($workspace, $submission)),
            $this->postJson($this->cancelUrl($workspace, $submission)),
        ] as $response) {
            $response->assertUnauthorized();
        }
    }

    /** @return array{User, Workspace, User} */
    private function setupWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner]);
        $requester = User::factory()->create();
        $this->member($workspace, $owner, WorkspaceRole::Owner);
        $this->member($workspace, $requester, WorkspaceRole::Requester);

        return [$owner, $workspace, $requester];
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

    private function requestType(Workspace $workspace, User $creator, string $name = 'Purchase Request', bool $active = true): RequestType
    {
        return RequestType::factory()->create([
            'workspace_id' => $workspace,
            'created_by' => $creator,
            'name' => $name,
            'is_active' => $active,
        ]);
    }

    private function activeWorkflow(RequestType $type, User $creator, int $version = 1, string $name = 'Approval'): Workflow
    {
        $workflow = Workflow::factory()->create([
            'workspace_id' => $type->workspace_id,
            'request_type_id' => $type,
            'created_by' => $creator,
            'name' => $name,
            'version' => $version,
            'status' => WorkflowStatus::Active,
            'draft_guard' => null,
            'active_guard' => 1,
            'published_at' => now(),
        ]);
        $this->step($workflow);

        return $workflow;
    }

    private function draftWorkflow(RequestType $type, User $creator, int $version = 1, string $name = 'Approval'): Workflow
    {
        return Workflow::factory()->create([
            'workspace_id' => $type->workspace_id,
            'request_type_id' => $type,
            'created_by' => $creator,
            'name' => $name,
            'version' => $version,
            'status' => WorkflowStatus::Draft,
            'draft_guard' => 1,
            'active_guard' => null,
            'published_at' => null,
        ]);
    }

    private function step(Workflow $workflow): WorkflowStep
    {
        return WorkflowStep::factory()->create([
            'workflow_id' => $workflow,
            'approver_type' => 'role',
            'approver_role' => 'owner',
            'approver_user_id' => null,
        ]);
    }

    private function field(
        RequestType $type,
        string $key,
        RequestFieldType $fieldType,
        bool $required = false,
        ?array $config = null,
        int $position = 1,
        ?string $label = null,
    ): RequestTypeField {
        return RequestTypeField::factory()->create([
            'request_type_id' => $type,
            'key' => $key,
            'label' => $label ?? str($key)->headline()->toString(),
            'type' => $fieldType,
            'is_required' => $required,
            'config' => $config,
            'position' => $position,
        ]);
    }

    private function allFieldTypes(RequestType $type): void
    {
        $this->field($type, 'text', RequestFieldType::Text, config: ['min_length' => 2, 'max_length' => 10]);
        $this->field($type, 'textarea', RequestFieldType::Textarea, config: ['min_length' => 2, 'max_length' => 10], position: 2);
        $this->field($type, 'number', RequestFieldType::Number, config: ['min' => 0, 'max' => 10], position: 3);
        $this->field($type, 'decimal', RequestFieldType::Decimal, config: ['min' => 0, 'max' => 10], position: 4);
        $this->field($type, 'boolean', RequestFieldType::Boolean, position: 5);
        $this->field($type, 'date', RequestFieldType::Date, position: 6);
        $this->field($type, 'datetime', RequestFieldType::Datetime, position: 7);
        $this->field($type, 'select', RequestFieldType::Select, config: $this->fieldOptions(), position: 8);
        $this->field($type, 'multiselect', RequestFieldType::Multiselect, config: $this->fieldOptions(), position: 9);
        $this->field($type, 'email', RequestFieldType::Email, config: ['max_length' => 50], position: 10);
        $this->field($type, 'url', RequestFieldType::Url, config: ['max_length' => 100], position: 11);
    }

    /** @return array{options: list<array{value: string, label: string}>} */
    private function fieldOptions(): array
    {
        return ['options' => [
            ['value' => 'low', 'label' => 'Low'],
            ['value' => 'high', 'label' => 'High'],
        ]];
    }

    /** @param array<string, mixed> $payload */
    private function submission(
        Workspace $workspace,
        RequestType $type,
        User $creator,
        RequestStatus $status = RequestStatus::Draft,
        array $payload = [],
        ?Workflow $workflow = null,
        ?array $snapshot = null,
    ): RequestSubmission {
        return RequestSubmission::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $type,
            'created_by' => $creator,
            'status' => $status,
            'payload' => $payload,
            'workflow_id' => $workflow,
            'definition_snapshot' => $snapshot,
            'submitted_at' => $status === RequestStatus::Submitted ? now() : null,
            'cancelled_at' => $status === RequestStatus::Cancelled ? now() : null,
        ]);
    }

    private function authenticate(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    private function catalogUrl(Workspace $workspace): string
    {
        return "/api/v1/workspaces/{$workspace->id}/request-catalog";
    }

    private function createUrl(Workspace $workspace, RequestType $type): string
    {
        return "/api/v1/workspaces/{$workspace->id}/request-types/{$type->id}/requests";
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

    private function requestTypeUrl(Workspace $workspace, RequestType $type): string
    {
        return "/api/v1/workspaces/{$workspace->id}/request-types/{$type->id}";
    }
}

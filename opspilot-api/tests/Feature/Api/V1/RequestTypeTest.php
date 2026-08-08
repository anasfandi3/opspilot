<?php

namespace Tests\Feature\Api\V1;

use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestFieldType;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RequestTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_create_request_types_but_requester_cannot(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);
        $this->authenticate($owner);
        $this->postJson($this->typesUrl($workspace), ['name' => 'Purchase Request'])
            ->assertCreated()->assertJsonPath('data.slug', 'purchase-request')
            ->assertJsonPath('data.is_active', true);

        $admin = User::factory()->create();
        $this->member($workspace, $admin, WorkspaceRole::Admin);
        $this->authenticate($admin);
        $this->postJson($this->typesUrl($workspace), ['name' => 'Access Request'])->assertCreated();

        $requester = User::factory()->create();
        $this->member($workspace, $requester, WorkspaceRole::Requester);
        $this->authenticate($requester);
        $this->postJson($this->typesUrl($workspace), ['name' => 'Denied'])->assertForbidden();
    }

    public function test_view_permission_controls_workspace_isolated_list_and_show(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $workspaceA = $this->workspace($ownerA);
        $workspaceB = $this->workspace($ownerB);
        $visible = $this->requestType($workspaceA, $ownerA, 'Visible');
        $hidden = $this->requestType($workspaceB, $ownerB, 'Hidden');
        $viewer = User::factory()->create();
        $this->member($workspaceA, $viewer, WorkspaceRole::Requester);
        app(WorkspacePermissions::class)->within($workspaceA, fn () => $viewer->givePermissionTo(WorkspacePermission::RequestTypesView->value));
        $this->authenticate($viewer);

        $this->getJson($this->typesUrl($workspaceA))->assertOk()
            ->assertJsonFragment(['id' => $visible->id])->assertJsonMissing(['id' => $hidden->id]);
        $this->getJson($this->typeUrl($workspaceA, $visible))->assertOk();
        $this->getJson($this->typeUrl($workspaceA, $hidden))->assertNotFound();
        $this->getJson($this->typesUrl($workspaceB))->assertForbidden();
    }

    public function test_slugs_are_workspace_scoped_readable_and_stable_after_rename(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $workspaceA = $this->workspace($ownerA);
        $workspaceB = $this->workspace($ownerB);
        $this->authenticate($ownerA);
        $first = $this->postJson($this->typesUrl($workspaceA), ['name' => 'Purchase Request'])->assertCreated();
        $second = $this->postJson($this->typesUrl($workspaceA), ['name' => 'Purchase Request'])->assertCreated();
        $this->assertSame('purchase-request', $first->json('data.slug'));
        $this->assertSame('purchase-request-2', $second->json('data.slug'));

        $this->authenticate($ownerB);
        $this->postJson($this->typesUrl($workspaceB), ['name' => 'Purchase Request'])
            ->assertCreated()->assertJsonPath('data.slug', 'purchase-request');

        $this->authenticate($ownerA);
        $requestType = RequestType::query()->findOrFail($first->json('data.id'));
        $this->patchJson($this->typeUrl($workspaceA, $requestType), ['name' => 'Renamed', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.slug', 'purchase-request')->assertJsonPath('data.is_active', false);
    }

    public function test_request_type_creation_recovers_from_a_concurrent_slug_collision(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);
        $collisionCreated = false;
        RequestType::creating(function (RequestType $requestType) use (&$collisionCreated, $workspace, $owner): void {
            if ($collisionCreated || $requestType->slug !== 'operations') {
                return;
            }
            $collisionCreated = true;
            RequestType::withoutEvents(function () use ($workspace, $owner): void {
                $competing = new RequestType(['name' => 'Competing']);
                $competing->forceFill(['slug' => 'operations']);
                $competing->workspace()->associate($workspace);
                $competing->creator()->associate($owner);
                $competing->save();
            });
        });
        $this->authenticate($owner);

        try {
            $this->postJson($this->typesUrl($workspace), ['name' => 'Operations'])
                ->assertCreated()->assertJsonPath('data.slug', 'operations-2');
        } finally {
            RequestType::flushEventListeners();
        }
        $this->assertTrue($collisionCreated);
    }

    public function test_update_and_delete_are_authorized_and_delete_cascades_fields(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);
        $requestType = $this->requestType($workspace, $owner);
        $field = $this->field($requestType, 'summary', 1);
        $requester = User::factory()->create();
        $this->member($workspace, $requester, WorkspaceRole::Requester);
        $this->authenticate($requester);
        $this->patchJson($this->typeUrl($workspace, $requestType), ['name' => 'Denied'])->assertForbidden();

        $this->authenticate($owner);
        $this->patchJson($this->typeUrl($workspace, $requestType), [
            'workspace_id' => 999, 'slug' => 'changed', 'created_by' => $requester->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['workspace_id', 'slug', 'created_by']);
        $this->deleteJson($this->typeUrl($workspace, $requestType))->assertOk();
        $this->assertModelMissing($requestType);
        $this->assertModelMissing($field);
    }

    public function test_all_supported_field_types_can_be_created_and_positions_append_deterministically(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);
        $requestType = $this->requestType($workspace, $owner);
        $this->authenticate($owner);

        foreach (RequestFieldType::cases() as $index => $type) {
            $config = match ($type) {
                RequestFieldType::Text, RequestFieldType::Textarea => ['min_length' => 0, 'max_length' => 100],
                RequestFieldType::Number, RequestFieldType::Decimal => ['min' => 0, 'max' => 10],
                RequestFieldType::Select, RequestFieldType::Multiselect => ['options' => [['value' => 'one', 'label' => 'One']]],
                RequestFieldType::Email, RequestFieldType::Url => ['max_length' => 255],
                default => null,
            };
            $this->postJson($this->fieldsUrl($workspace, $requestType), [
                'key' => 'field_'.$index, 'label' => $type->value, 'type' => $type->value, 'config' => $config,
            ])->assertCreated()->assertJsonPath('data.position', $index + 1);
        }

        $this->assertSame(range(1, count(RequestFieldType::cases())), $requestType->fields()->pluck('position')->all());
    }

    public function test_field_keys_are_snake_case_unique_per_type_and_scoped_between_types(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);
        $first = $this->requestType($workspace, $owner, 'First');
        $second = $this->requestType($workspace, $owner, 'Second');
        $this->authenticate($owner);
        $payload = ['key' => 'cost_center', 'label' => 'Cost center', 'type' => 'text'];
        $this->postJson($this->fieldsUrl($workspace, $first), $payload)->assertCreated();
        $this->postJson($this->fieldsUrl($workspace, $first), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->postJson($this->fieldsUrl($workspace, $second), $payload)->assertCreated();
        $this->postJson($this->fieldsUrl($workspace, $first), [...$payload, 'key' => 'Cost-Center'])
            ->assertUnprocessable()->assertJsonValidationErrors('key');
    }

    public function test_type_aware_config_rejects_unsupported_keys_and_invalid_ranges(): void
    {
        [$owner, $workspace, $requestType] = $this->setupManagedType();
        $this->authenticate($owner);
        $url = $this->fieldsUrl($workspace, $requestType);

        $invalid = [
            ['key' => 'text_range', 'label' => 'Text', 'type' => 'text', 'config' => ['min_length' => 10, 'max_length' => 2]],
            ['key' => 'number_range', 'label' => 'Number', 'type' => 'number', 'config' => ['min' => 10, 'max' => 2]],
            ['key' => 'boolean_extra', 'label' => 'Boolean', 'type' => 'boolean', 'config' => ['anything' => true]],
            ['key' => 'email_extra', 'label' => 'Email', 'type' => 'email', 'config' => ['min_length' => 1]],
        ];
        foreach ($invalid as $payload) {
            $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('config');
        }
    }

    public function test_select_configuration_requires_nonempty_unique_well_formed_options(): void
    {
        [$owner, $workspace, $requestType] = $this->setupManagedType();
        $this->authenticate($owner);
        $url = $this->fieldsUrl($workspace, $requestType);
        $base = ['label' => 'Choice', 'type' => 'select'];

        $this->postJson($url, [...$base, 'key' => 'omitted_config'])->assertUnprocessable();
        $this->postJson($url, [...$base, 'key' => 'missing_options', 'config' => []])->assertUnprocessable();
        $this->postJson($url, [...$base, 'key' => 'empty_options', 'config' => ['options' => []]])->assertUnprocessable();
        $this->postJson($url, [...$base, 'key' => 'duplicate_options', 'config' => ['options' => [
            ['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'Again'],
        ]]])->assertUnprocessable();
        $this->postJson($url, [...$base, 'key' => 'extra_option_key', 'config' => ['options' => [
            ['value' => 'a', 'label' => 'A', 'extra' => true],
        ]]])->assertUnprocessable();
        $this->postJson($url, [...$base, 'key' => 'valid_options', 'config' => ['options' => [
            ['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B'],
        ]]])->assertCreated();
    }

    public function test_field_key_type_position_and_parent_are_immutable(): void
    {
        [$owner, $workspace, $requestType] = $this->setupManagedType();
        $field = $this->field($requestType, 'original_key', 1);
        $this->authenticate($owner);
        $url = $this->fieldUrl($workspace, $requestType, $field);

        $this->patchJson($url, [
            'key' => 'changed', 'type' => 'number', 'position' => 10, 'request_type_id' => 999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['key', 'type', 'position', 'request_type_id']);
        $this->patchJson($url, ['label' => 'Changed', 'is_required' => true, 'config' => ['max_length' => 50]])
            ->assertOk()->assertJsonPath('data.key', 'original_key')->assertJsonPath('data.type', 'text');
    }

    public function test_field_management_is_authorized_and_nested_bindings_prevent_cross_type_access(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $workspaceA = $this->workspace($ownerA);
        $workspaceB = $this->workspace($ownerB);
        $typeA = $this->requestType($workspaceA, $ownerA);
        $typeB = $this->requestType($workspaceB, $ownerB);
        $fieldA = $this->field($typeA, 'local', 1);
        $fieldB = $this->field($typeB, 'foreign', 1);
        $requester = User::factory()->create();
        $this->member($workspaceA, $requester, WorkspaceRole::Requester);
        $this->authenticate($requester);
        $this->postJson($this->fieldsUrl($workspaceA, $typeA), ['key' => 'denied', 'label' => 'Denied', 'type' => 'text'])->assertForbidden();

        $this->authenticate($ownerA);
        $this->deleteJson($this->fieldUrl($workspaceA, $typeA, $fieldA))->assertOk();
        $this->assertModelMissing($fieldA);
        $this->patchJson($this->fieldUrl($workspaceA, $typeA, $fieldB), ['label' => 'Cross'])->assertNotFound();
        $this->deleteJson($this->fieldUrl($workspaceA, $typeA, $fieldB))->assertNotFound();
        $this->postJson($this->fieldsUrl($workspaceA, $typeB), ['key' => 'cross', 'label' => 'Cross', 'type' => 'text'])->assertNotFound();
    }

    public function test_full_set_reorder_succeeds_and_is_deterministic(): void
    {
        [$owner, $workspace, $requestType] = $this->setupManagedType();
        $first = $this->field($requestType, 'first', 1);
        $second = $this->field($requestType, 'second', 2);
        $third = $this->field($requestType, 'third', 3);
        $this->authenticate($owner);

        $this->postJson($this->reorderUrl($workspace, $requestType), [
            'field_ids' => [$third->id, $first->id, $second->id],
        ])->assertOk()->assertJsonPath('data.0.id', $third->id)->assertJsonPath('data.2.id', $second->id);

        $this->assertSame([$third->id, $first->id, $second->id], $requestType->fields()->pluck('id')->all());
        $this->assertSame([1, 2, 3], $requestType->fields()->pluck('position')->all());
    }

    public function test_reorder_rejects_duplicates_foreign_ids_and_omitted_fields(): void
    {
        [$owner, $workspace, $requestType] = $this->setupManagedType();
        $first = $this->field($requestType, 'first', 1);
        $second = $this->field($requestType, 'second', 2);
        $other = $this->requestType($workspace, $owner, 'Other');
        $foreign = $this->field($other, 'foreign', 1);
        $this->authenticate($owner);
        $url = $this->reorderUrl($workspace, $requestType);

        $this->postJson($url, ['field_ids' => [$first->id, $first->id]])->assertUnprocessable();
        $this->postJson($url, ['field_ids' => [$first->id, $foreign->id]])->assertUnprocessable();
        $this->postJson($url, ['field_ids' => [$first->id]])->assertUnprocessable();
        $this->assertSame([$first->id, $second->id], $requestType->fields()->pluck('id')->all());
    }

    public function test_reorder_rolls_back_all_positions_when_an_update_fails(): void
    {
        [$owner, $workspace, $requestType] = $this->setupManagedType();
        $first = $this->field($requestType, 'first', 1);
        $second = $this->field($requestType, 'second', 2);
        RequestTypeField::updating(static function (RequestTypeField $field): void {
            if ($field->key === 'first') {
                throw new RuntimeException('Simulated reorder failure.');
            }
        });
        $this->authenticate($owner);
        $this->withoutExceptionHandling();

        try {
            $this->postJson($this->reorderUrl($workspace, $requestType), ['field_ids' => [$second->id, $first->id]]);
            $this->fail('The simulated reorder failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated reorder failure.', $exception->getMessage());
            $this->assertSame([1, 2], RequestTypeField::query()->whereBelongsTo($requestType)->orderBy('id')->pluck('position')->all());
        } finally {
            RequestTypeField::flushEventListeners();
        }
    }

    public function test_request_type_endpoints_require_authentication(): void
    {
        $workspace = Workspace::factory()->create();
        $requestType = RequestType::factory()->create(['workspace_id' => $workspace]);
        $field = RequestTypeField::factory()->create(['request_type_id' => $requestType]);
        $responses = [
            $this->getJson($this->typesUrl($workspace)),
            $this->postJson($this->typesUrl($workspace), ['name' => 'No auth']),
            $this->getJson($this->typeUrl($workspace, $requestType)),
            $this->patchJson($this->typeUrl($workspace, $requestType), ['name' => 'No auth']),
            $this->deleteJson($this->typeUrl($workspace, $requestType)),
            $this->postJson($this->fieldsUrl($workspace, $requestType), ['key' => 'x', 'label' => 'X', 'type' => 'text']),
            $this->patchJson($this->fieldUrl($workspace, $requestType, $field), ['label' => 'X']),
            $this->deleteJson($this->fieldUrl($workspace, $requestType, $field)),
            $this->postJson($this->reorderUrl($workspace, $requestType), ['field_ids' => [$field->id]]),
        ];
        foreach ($responses as $response) {
            $response->assertUnauthorized();
        }
    }

    /** @return array{User, Workspace, RequestType} */
    private function setupManagedType(): array
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

    private function requestType(Workspace $workspace, User $creator, string $name = 'Request Type'): RequestType
    {
        return RequestType::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->randomNumber(6),
        ]);
    }

    private function field(RequestType $requestType, string $key, int $position): RequestTypeField
    {
        return RequestTypeField::factory()->create([
            'request_type_id' => $requestType->id,
            'key' => $key,
            'position' => $position,
        ]);
    }

    private function authenticate(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    private function typesUrl(Workspace $workspace): string
    {
        return "/api/v1/workspaces/{$workspace->id}/request-types";
    }

    private function typeUrl(Workspace $workspace, RequestType $requestType): string
    {
        return $this->typesUrl($workspace)."/{$requestType->id}";
    }

    private function fieldsUrl(Workspace $workspace, RequestType $requestType): string
    {
        return $this->typeUrl($workspace, $requestType).'/fields';
    }

    private function fieldUrl(Workspace $workspace, RequestType $requestType, RequestTypeField $field): string
    {
        return $this->fieldsUrl($workspace, $requestType)."/{$field->id}";
    }

    private function reorderUrl(Workspace $workspace, RequestType $requestType): string
    {
        return $this->fieldsUrl($workspace, $requestType).'/reorder';
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_workspace_with_owner_membership_and_current_workspace(): void
    {
        $user = User::factory()->create();
        $this->authenticate($user);

        $response = $this->postJson('/api/v1/workspaces', ['name' => 'Operations Team']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Operations Team')
            ->assertJsonPath('data.slug', 'operations-team')
            ->assertJsonPath('data.owner_id', $user->id)
            ->assertJsonPath('data.role', WorkspaceRole::Owner->value)
            ->assertJsonPath('message', 'Workspace created successfully.');

        $workspace = Workspace::query()->sole();
        $membership = WorkspaceMembership::query()->sole();
        $this->assertSame($workspace->id, $membership->workspace_id);
        $this->assertSame($user->id, $membership->user_id);
        $this->assertSame(WorkspaceRole::Owner, $membership->role);
        $this->assertSame($workspace->id, $user->fresh()->current_workspace_id);
    }

    public function test_workspace_creation_is_atomic(): void
    {
        $user = User::factory()->create();
        $this->authenticate($user);
        WorkspaceMembership::creating(static function (): never {
            throw new RuntimeException('Simulated membership failure.');
        });
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/v1/workspaces', ['name' => 'Rollback Team']);
            $this->fail('The simulated membership failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated membership failure.', $exception->getMessage());
        } finally {
            WorkspaceMembership::flushEventListeners();
        }

        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount('workspace_user', 0);
        $this->assertNull($user->fresh()->current_workspace_id);
    }

    public function test_workspace_slugs_are_unique(): void
    {
        $user = User::factory()->create();
        $this->authenticate($user);

        $this->postJson('/api/v1/workspaces', ['name' => 'Operations'])->assertCreated();
        $this->postJson('/api/v1/workspaces', ['name' => 'Operations'])->assertCreated();

        $this->assertEqualsCanonicalizing(
            ['operations', 'operations-2'],
            Workspace::query()->pluck('slug')->all(),
        );
    }

    public function test_workspace_creation_recovers_when_slug_is_taken_between_selection_and_insert(): void
    {
        $user = User::factory()->create();
        $this->authenticate($user);
        $collisionCreated = false;

        Workspace::creating(function (Workspace $workspace) use (&$collisionCreated, $user): void {
            if ($collisionCreated || $workspace->slug !== 'operations') {
                return;
            }

            $collisionCreated = true;
            Workspace::withoutEvents(function () use ($user): void {
                $competingWorkspace = new Workspace([
                    'name' => 'Competing Operations',
                    'slug' => 'operations',
                ]);
                $competingWorkspace->owner()->associate($user);
                $competingWorkspace->save();
            });
        });

        try {
            $response = $this->postJson('/api/v1/workspaces', ['name' => 'Operations']);
        } finally {
            Workspace::flushEventListeners();
        }

        $response->assertCreated()->assertJsonPath('data.slug', 'operations-2');
        $this->assertTrue($collisionCreated);
        $this->assertDatabaseHas('workspaces', ['slug' => 'operations']);
        $this->assertDatabaseHas('workspaces', ['slug' => 'operations-2']);
    }

    public function test_user_lists_only_workspaces_where_they_are_a_member(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $first = $this->workspaceOwnedBy($user, 'First');
        $second = $this->workspaceOwnedBy($owner, 'Second');
        $unrelated = $this->workspaceOwnedBy($owner, 'Unrelated');
        $this->addMember($second, $user, WorkspaceRole::Member);
        $this->authenticate($user);

        $response = $this->getJson('/api/v1/workspaces')->assertOk()->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
        $response->assertJsonMissing(['id' => $unrelated->id]);
    }

    public function test_member_can_view_workspace_with_their_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);
        $this->addMember($workspace, $member, WorkspaceRole::Member);
        $this->authenticate($member);

        $this->getJson("/api/v1/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $workspace->id)
            ->assertJsonPath('data.role', WorkspaceRole::Member->value);
    }

    public function test_unrelated_user_cannot_view_workspace(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $this->authenticate(User::factory()->create());

        $this->getJson("/api/v1/workspaces/{$workspace->id}")->assertForbidden();
    }

    public function test_owner_can_rename_workspace_and_slug_remains_stable(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner, 'Original Name');
        $originalSlug = $workspace->slug;
        $this->authenticate($owner);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed Workspace'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Workspace')
            ->assertJsonPath('data.slug', $originalSlug);
    }

    public function test_admin_can_rename_workspace(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $admin = User::factory()->create();
        $this->addMember($workspace, $admin, WorkspaceRole::Admin);
        $this->authenticate($admin);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Admin Rename'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Admin Rename');
    }

    public function test_member_cannot_rename_workspace(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $member = User::factory()->create();
        $this->addMember($workspace, $member, WorkspaceRole::Member);
        $this->authenticate($member);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Forbidden Rename'])
            ->assertForbidden();
    }

    public function test_member_can_switch_to_workspace_where_they_belong(): void
    {
        $user = User::factory()->create();
        $first = $this->workspaceOwnedBy($user, 'First');
        $second = $this->workspaceOwnedBy(User::factory()->create(), 'Second');
        $this->addMember($second, $user, WorkspaceRole::Member);
        $user->forceFill(['current_workspace_id' => $first->id])->save();
        $this->authenticate($user);

        $this->postJson("/api/v1/workspaces/{$second->id}/switch")
            ->assertOk()
            ->assertJsonPath('data.id', $second->id);

        $this->assertSame($second->id, $user->fresh()->current_workspace_id);
    }

    public function test_user_cannot_switch_to_unrelated_workspace(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $user = User::factory()->create();
        $this->authenticate($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/switch")->assertForbidden();
        $this->assertNull($user->fresh()->current_workspace_id);
    }

    public function test_owner_cannot_leave_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);
        $this->authenticate($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/leave")->assertForbidden();
        $this->assertDatabaseHas('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    }

    public function test_admin_can_leave_workspace(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $admin = User::factory()->create();
        $this->addMember($workspace, $admin, WorkspaceRole::Admin);
        $this->authenticate($admin);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/leave")->assertOk();
        $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    }

    public function test_member_can_leave_workspace(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $member = User::factory()->create();
        $this->addMember($workspace, $member, WorkspaceRole::Member);
        $this->authenticate($member);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/leave")->assertOk();
        $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $member->id]);
    }

    public function test_current_workspace_changes_to_another_membership_after_leaving(): void
    {
        $user = User::factory()->create();
        $leaving = $this->workspaceOwnedBy(User::factory()->create(), 'Leaving');
        $remaining = $this->workspaceOwnedBy(User::factory()->create(), 'Remaining');
        $this->addMember($leaving, $user, WorkspaceRole::Member);
        $this->addMember($remaining, $user, WorkspaceRole::Member);
        $user->forceFill(['current_workspace_id' => $leaving->id])->save();
        $this->authenticate($user);

        $this->postJson("/api/v1/workspaces/{$leaving->id}/leave")->assertOk();

        $this->assertSame($remaining->id, $user->fresh()->current_workspace_id);
    }

    public function test_current_workspace_fallback_uses_membership_id_when_joined_at_matches(): void
    {
        $user = User::factory()->create();
        $leaving = $this->workspaceOwnedBy(User::factory()->create(), 'Leaving');
        $firstFallback = $this->workspaceOwnedBy(User::factory()->create(), 'First Fallback');
        $secondFallback = $this->workspaceOwnedBy(User::factory()->create(), 'Second Fallback');
        $this->addMember($leaving, $user, WorkspaceRole::Member);
        $firstMembership = $this->addMember($firstFallback, $user, WorkspaceRole::Member);
        $secondMembership = $this->addMember($secondFallback, $user, WorkspaceRole::Member);
        $joinedAt = now()->subDay();
        $firstMembership->update(['joined_at' => $joinedAt]);
        $secondMembership->update(['joined_at' => $joinedAt]);
        $user->forceFill(['current_workspace_id' => $leaving->id])->save();
        $this->authenticate($user);

        $this->postJson("/api/v1/workspaces/{$leaving->id}/leave")->assertOk();

        $this->assertLessThan($secondMembership->id, $firstMembership->id);
        $this->assertSame($firstFallback->id, $user->fresh()->current_workspace_id);
    }

    public function test_current_workspace_becomes_null_when_user_leaves_their_only_workspace(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $member = User::factory()->create();
        $this->addMember($workspace, $member, WorkspaceRole::Member);
        $member->forceFill(['current_workspace_id' => $workspace->id])->save();
        $this->authenticate($member);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/leave")->assertOk();

        $this->assertNull($member->fresh()->current_workspace_id);
    }

    public function test_workspace_member_can_list_members(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);
        $workspace = $this->workspaceOwnedBy($owner);
        $this->addMember($workspace, $member, WorkspaceRole::Member);
        $this->authenticate($member);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/members")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $owner->id, 'role' => WorkspaceRole::Owner->value])
            ->assertJsonFragment(['id' => $member->id, 'role' => WorkspaceRole::Member->value]);
    }

    public function test_unrelated_user_cannot_list_workspace_members(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $this->authenticate(User::factory()->create());

        $this->getJson("/api/v1/workspaces/{$workspace->id}/members")->assertForbidden();
    }

    public function test_owner_can_remove_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);
        $this->addMember($workspace, $member, WorkspaceRole::Member);
        $this->authenticate($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")->assertOk();
        $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $member->id]);
    }

    public function test_admin_can_remove_members_and_other_admins(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create();
        $member = User::factory()->create();
        $this->addMember($workspace, $admin, WorkspaceRole::Admin);
        $this->addMember($workspace, $otherAdmin, WorkspaceRole::Admin);
        $this->addMember($workspace, $member, WorkspaceRole::Member);
        $this->authenticate($admin);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$otherAdmin->id}")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")->assertOk();

        $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $otherAdmin->id]);
        $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $member->id]);
    }

    public function test_member_cannot_remove_another_member(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $this->addMember($workspace, $actor, WorkspaceRole::Member);
        $this->addMember($workspace, $target, WorkspaceRole::Member);
        $this->authenticate($actor);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$target->id}")->assertForbidden();
    }

    public function test_owner_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);
        $this->addMember($workspace, $admin, WorkspaceRole::Admin);
        $this->authenticate($admin);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$owner->id}")->assertForbidden();
        $this->assertDatabaseHas('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    }

    public function test_user_cannot_remove_themselves_through_member_removal_endpoint(): void
    {
        $workspace = $this->workspaceOwnedBy(User::factory()->create());
        $admin = User::factory()->create();
        $this->addMember($workspace, $admin, WorkspaceRole::Admin);
        $this->authenticate($admin);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$admin->id}")->assertForbidden();
    }

    public function test_unrelated_workspace_user_id_cannot_bypass_removal_authorization(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);
        $unrelated = User::factory()->create();
        $this->authenticate($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$unrelated->id}")->assertForbidden();
    }

    public function test_removed_users_current_workspace_is_reassigned(): void
    {
        $owner = User::factory()->create();
        $removed = User::factory()->create();
        $removingFrom = $this->workspaceOwnedBy($owner, 'Removing From');
        $remaining = $this->workspaceOwnedBy(User::factory()->create(), 'Remaining');
        $this->addMember($removingFrom, $removed, WorkspaceRole::Member);
        $this->addMember($remaining, $removed, WorkspaceRole::Member);
        $removed->forceFill(['current_workspace_id' => $removingFrom->id])->save();
        $this->authenticate($owner);

        $this->deleteJson("/api/v1/workspaces/{$removingFrom->id}/members/{$removed->id}")->assertOk();

        $this->assertSame($remaining->id, $removed->fresh()->current_workspace_id);
    }

    public function test_removed_users_current_workspace_is_cleared_without_other_memberships(): void
    {
        $owner = User::factory()->create();
        $removed = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);
        $this->addMember($workspace, $removed, WorkspaceRole::Member);
        $removed->forceFill(['current_workspace_id' => $workspace->id])->save();
        $this->authenticate($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$removed->id}")->assertOk();

        $this->assertNull($removed->fresh()->current_workspace_id);
    }

    public function test_duplicate_memberships_are_prevented(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);

        $this->expectException(QueryException::class);
        $this->addMember($workspace, $owner, WorkspaceRole::Owner);
    }

    public function test_unauthenticated_workspace_requests_are_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);
        $this->addMember($workspace, $member, WorkspaceRole::Member);

        $responses = [
            $this->getJson('/api/v1/workspaces'),
            $this->postJson('/api/v1/workspaces', ['name' => 'No Auth']),
            $this->getJson("/api/v1/workspaces/{$workspace->id}"),
            $this->patchJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'No Auth']),
            $this->postJson("/api/v1/workspaces/{$workspace->id}/switch"),
            $this->postJson("/api/v1/workspaces/{$workspace->id}/leave"),
            $this->getJson("/api/v1/workspaces/{$workspace->id}/members"),
            $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}"),
        ];

        foreach ($responses as $response) {
            $response->assertUnauthorized();
        }
    }

    private function authenticate(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('Test device')->plainTextToken);
    }

    private function workspaceOwnedBy(User $owner, string $name = 'Workspace'): Workspace
    {
        $workspace = Workspace::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->randomNumber(6),
            'owner_id' => $owner->id,
        ]);
        $this->addMember($workspace, $owner, WorkspaceRole::Owner);

        return $workspace;
    }

    private function addMember(Workspace $workspace, User $user, WorkspaceRole $role): WorkspaceMembership
    {
        return WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use App\Support\WorkspaceRoleMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePermissionExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_resources_expose_tenant_scoped_effective_permissions_from_the_role_map(): void
    {
        $user = User::factory()->create();
        $adminWorkspace = Workspace::factory()->create();
        $requesterWorkspace = Workspace::factory()->create();
        $inaccessibleWorkspace = Workspace::factory()->create();
        $this->member($adminWorkspace, $user, WorkspaceRole::Admin);
        $this->member($requesterWorkspace, $user, WorkspaceRole::Requester);
        $this->authenticate($user);

        $response = $this->getJson('/api/v1/workspaces')->assertOk()->assertJsonCount(2, 'data');
        $workspaces = collect($response->json('data'))->keyBy('id');

        $this->assertSame(WorkspaceRole::Admin->value, $workspaces[$adminWorkspace->id]['role']);
        $this->assertSame($this->permissions(WorkspaceRole::Admin), $workspaces[$adminWorkspace->id]['permissions']);
        $this->assertSame(WorkspaceRole::Requester->value, $workspaces[$requesterWorkspace->id]['role']);
        $this->assertSame($this->permissions(WorkspaceRole::Requester), $workspaces[$requesterWorkspace->id]['permissions']);
        $this->assertNotSame(
            $workspaces[$adminWorkspace->id]['permissions'],
            $workspaces[$requesterWorkspace->id]['permissions'],
        );
        $this->assertFalse($workspaces->has($inaccessibleWorkspace->id));

        $this->getJson("/api/v1/workspaces/{$adminWorkspace->id}")
            ->assertOk()
            ->assertJsonPath('data.permissions', $this->permissions(WorkspaceRole::Admin));
        $this->getJson("/api/v1/workspaces/{$requesterWorkspace->id}")
            ->assertOk()
            ->assertJsonPath('data.permissions', $this->permissions(WorkspaceRole::Requester));
        $this->getJson("/api/v1/workspaces/{$inaccessibleWorkspace->id}")->assertForbidden();
    }

    public function test_removed_membership_no_longer_exposes_workspace_or_permissions(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->member($workspace, $user, WorkspaceRole::Admin);
        app(WorkspacePermissions::class)->remove($user, $workspace);
        WorkspaceMembership::query()->whereBelongsTo($workspace)->whereBelongsTo($user)->delete();
        $this->authenticate($user);

        $this->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonMissing(['id' => $workspace->id]);
        $this->getJson("/api/v1/workspaces/{$workspace->id}")->assertForbidden();
    }

    private function member(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        app(SynchronizeWorkspacePermissions::class)->handle($workspace);
        WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'joined_at' => now(),
        ]);
        app(WorkspacePermissions::class)->assign($user, $workspace, $role);
    }

    /** @return list<string> */
    private function permissions(WorkspaceRole $role): array
    {
        return array_map(
            static fn (WorkspacePermission $permission): string => $permission->value,
            WorkspaceRoleMap::permissions($role),
        );
    }

    private function authenticate(User $user): void
    {
        $this->withToken($user->createToken('Test device')->plainTextToken);
    }
}

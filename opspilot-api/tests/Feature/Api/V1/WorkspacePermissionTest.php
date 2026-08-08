<?php

namespace Tests\Feature\Api\V1;

use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Http\Middleware\ResolveWorkspaceContext;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspacePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_synchronization_is_idempotent_and_roles_receive_expected_permissions(): void
    {
        $workspace = Workspace::factory()->create();
        $sync = app(SynchronizeWorkspacePermissions::class);
        $sync->handle($workspace);
        $sync->handle($workspace);

        $this->assertSame(count(WorkspacePermission::cases()), Permission::query()->count());
        $this->assertSame(count(WorkspaceRole::cases()), Role::query()->where('workspace_id', $workspace->id)->count());
        app(WorkspacePermissions::class)->within($workspace, function (): void {
            $this->assertTrue(Role::findByName('owner')->hasPermissionTo('audit_logs.view'));
            $this->assertTrue(Role::findByName('admin')->hasPermissionTo('invitations.create'));
            $this->assertTrue(Role::findByName('approver')->hasPermissionTo('approvals.act'));
            $this->assertFalse(Role::findByName('requester')->hasPermissionTo('workspace.update'));
            $this->assertTrue(Role::findByName('auditor')->hasPermissionTo('reports.view'));
        });
    }

    public function test_roles_are_independent_between_workspaces_and_admin_authority_does_not_leak(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $user = User::factory()->create();
        $a = $this->workspace($ownerA);
        $b = $this->workspace($ownerB);
        $this->member($a, $user, WorkspaceRole::Admin);
        $this->member($b, $user, WorkspaceRole::Requester);
        $user->forceFill(['current_workspace_id' => $a->id])->save();
        $this->withToken($user->createToken('test')->plainTextToken);

        $this->patchJson("/api/v1/workspaces/{$a->id}", ['name' => 'Allowed'])->assertOk();
        $this->patchJson("/api/v1/workspaces/{$b->id}", ['name' => 'Denied'])->assertForbidden();
        $this->assertSame(WorkspaceRole::Admin, app(WorkspacePermissions::class)->role($user, $a));
        $this->assertSame(WorkspaceRole::Requester, app(WorkspacePermissions::class)->role($user, $b));
    }

    public function test_default_restricted_roles_have_only_their_intended_access(): void
    {
        $workspace = $this->workspace(User::factory()->create());
        foreach ([WorkspaceRole::Approver, WorkspaceRole::Requester, WorkspaceRole::Auditor] as $role) {
            $user = User::factory()->create();
            $this->member($workspace, $user, $role);
            $this->app['auth']->forgetGuards();
            $this->withToken($user->createToken('test')->plainTextToken);
            $this->getJson("/api/v1/workspaces/{$workspace->id}/members")->assertOk();
            $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", ['email' => 'new@example.com', 'role' => 'requester'])->assertForbidden();
        }
    }

    public function test_legacy_membership_roles_map_to_scoped_defaults(): void
    {
        $this->assertSame(WorkspaceRole::Owner, WorkspaceRole::fromLegacy('owner'));
        $this->assertSame(WorkspaceRole::Admin, WorkspaceRole::fromLegacy('admin'));
        $this->assertSame(WorkspaceRole::Requester, WorkspaceRole::fromLegacy('member'));
    }

    public function test_workspace_middleware_restores_previous_team_id_after_a_successful_request(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);
        $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
        $this->withToken($owner->createToken('test')->plainTextToken);
        setPermissionsTeamId(987654);

        try {
            $this->getJson('/api/v1/workspaces')->assertOk();
            $this->assertSame(987654, getPermissionsTeamId());
        } finally {
            setPermissionsTeamId(null);
        }
    }

    public function test_workspace_middleware_restores_previous_team_id_when_the_next_handler_throws(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/v1/workspaces', 'GET');
        $request->setUserResolver(static fn (): User => $user);
        setPermissionsTeamId(456789);

        try {
            app(ResolveWorkspaceContext::class)->handle(
                $request,
                static fn (): never => throw new RuntimeException('Simulated downstream failure.'),
            );
            $this->fail('The simulated downstream failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated downstream failure.', $exception->getMessage());
            $this->assertSame(456789, getPermissionsTeamId());
        } finally {
            setPermissionsTeamId(null);
        }
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
}

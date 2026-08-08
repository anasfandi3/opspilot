<?php

namespace Tests\Feature\Api\V1;

use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_and_auditor_can_view_all_reporting_endpoints(): void
    {
        $workspace = $this->workspace(User::factory()->create());

        foreach ([WorkspaceRole::Owner, WorkspaceRole::Admin, WorkspaceRole::Auditor] as $role) {
            $user = $role === WorkspaceRole::Owner ? $workspace->owner : User::factory()->create();
            $this->member($workspace, $user, $role);
            $this->authenticate($user, $workspace);

            foreach ($this->urls($workspace) as $url) {
                $this->getJson($url)->assertOk();
            }
        }
    }

    public function test_requester_and_approver_are_forbidden_unless_reports_permission_is_explicitly_granted(): void
    {
        $workspace = $this->workspace(User::factory()->create());

        foreach ([WorkspaceRole::Requester, WorkspaceRole::Approver] as $role) {
            $user = User::factory()->create();
            $this->member($workspace, $user, $role);
            $this->authenticate($user, $workspace);
            $this->getJson($this->urls($workspace)[0])->assertForbidden();
        }

        $approver = User::factory()->create();
        $this->member($workspace, $approver, WorkspaceRole::Approver);
        app(WorkspacePermissions::class)->within(
            $workspace,
            fn () => $approver->givePermissionTo(WorkspacePermission::ReportsView->value),
        );
        $this->authenticate($approver, $workspace);
        $this->getJson($this->urls($workspace)[0])->assertOk();
    }

    public function test_removed_member_and_unauthenticated_user_are_rejected(): void
    {
        $workspace = $this->workspace(User::factory()->create());
        $auditor = User::factory()->create();
        $this->member($workspace, $auditor, WorkspaceRole::Auditor);
        WorkspaceMembership::query()->whereBelongsTo($workspace)->whereBelongsTo($auditor)->delete();
        $this->authenticate($auditor, $workspace);
        $this->getJson($this->urls($workspace)[0])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken('invalid-token');
        $this->getJson($this->urls($workspace)[0])->assertUnauthorized();
    }

    public function test_permission_in_one_workspace_does_not_authorize_another_workspace(): void
    {
        $user = User::factory()->create();
        $allowed = $this->workspace(User::factory()->create());
        $denied = $this->workspace(User::factory()->create());
        $this->member($allowed, $user, WorkspaceRole::Auditor);
        $this->member($denied, $user, WorkspaceRole::Requester);
        $this->authenticate($user, $allowed);

        $this->getJson($this->urls($allowed)[0])->assertOk();
        $this->getJson($this->urls($denied)[0])->assertForbidden();
    }

    private function workspace(User $owner): Workspace
    {
        $workspace = Workspace::factory()->create(['owner_id' => $owner]);
        $this->member($workspace, $owner, WorkspaceRole::Owner);

        return $workspace->load('owner');
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

    private function authenticate(User $user, Workspace $workspace): void
    {
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    /** @return list<string> */
    private function urls(Workspace $workspace): array
    {
        return [
            "/api/v1/workspaces/{$workspace->id}/dashboard",
            "/api/v1/workspaces/{$workspace->id}/reports/requests",
            "/api/v1/workspaces/{$workspace->id}/reports/approvals",
        ];
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Notifications\WorkspaceInvitationNotification;
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class WorkspaceInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_create_normalized_secure_invitations(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);
        $this->authenticate($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
            'email' => '  Invitee@Example.COM ', 'role' => 'approver',
        ])->assertCreated()->assertJsonPath('data.email', 'invitee@example.com');
        $token = $response->json('data.token');
        $invitation = WorkspaceInvitation::query()->sole();
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertNotSame($token, $invitation->token_hash);
        Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);

        $admin = User::factory()->create();
        $this->member($workspace, $admin, WorkspaceRole::Admin);
        $this->authenticate($admin);
        $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
            'email' => 'second@example.com', 'role' => 'requester',
        ])->assertCreated();
    }

    public function test_invitation_validation_rejects_owner_existing_members_and_active_duplicates(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);
        $this->authenticate($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", ['email' => 'a@example.com', 'role' => 'owner'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", ['email' => $owner->email, 'role' => 'admin'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $payload = ['email' => 'duplicate@example.com', 'role' => 'auditor'];
        $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", $payload)->assertCreated();
        $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_invitation_authorization_and_workspace_route_isolation_are_enforced(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $a = $this->workspace($ownerA);
        $b = $this->workspace($ownerB);
        $requester = User::factory()->create();
        $this->member($a, $requester, WorkspaceRole::Requester);
        $invitation = WorkspaceInvitation::factory()->create(['workspace_id' => $b, 'invited_by' => $ownerB]);
        $this->authenticate($requester);
        $this->postJson("/api/v1/workspaces/{$a->id}/invitations", ['email' => 'x@example.com', 'role' => 'requester'])->assertForbidden();

        $this->authenticate($ownerA);
        $this->getJson("/api/v1/workspaces/{$a->id}/invitations")->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson("/api/v1/workspaces/{$a->id}/invitations/{$invitation->id}")->assertNotFound();
    }

    public function test_resend_rotates_token_and_revocation_prevents_acceptance(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $workspace = $this->workspace($owner);
        $this->authenticate($owner);
        $created = $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", ['email' => $invitee->email, 'role' => 'requester'])->json('data');
        $oldToken = $created['token'];
        $resent = $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations/{$created['id']}/resend")->assertOk();
        $newToken = $resent->json('data.token');
        $this->assertNotSame($oldToken, $newToken);

        $this->authenticate($invitee);
        $this->postJson("/api/v1/invitations/{$oldToken}/accept")->assertUnprocessable();
        $this->authenticate($owner);
        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/invitations/{$created['id']}")->assertOk();
        $this->authenticate($invitee);
        $this->postJson("/api/v1/invitations/{$newToken}/accept")->assertUnprocessable();
    }

    public function test_acceptance_requires_matching_email_creates_membership_role_and_is_single_use(): void
    {
        [$workspace, $token, $invitation, $invitee] = $this->pendingInvitation();
        $wrong = User::factory()->create();
        $this->authenticate($wrong);
        $this->postJson("/api/v1/invitations/{$token}/accept")->assertUnprocessable();

        $this->authenticate($invitee);
        $this->postJson("/api/v1/invitations/{$token}/accept")->assertOk();
        $this->assertDatabaseHas('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $invitee->id]);
        $this->assertSame(WorkspaceRole::Approver, app(WorkspacePermissions::class)->role($invitee, $workspace));
        $this->assertSame($workspace->id, $invitee->fresh()->current_workspace_id);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->postJson("/api/v1/invitations/{$token}/accept")->assertUnprocessable();
    }

    public function test_acceptance_rejects_an_existing_member_without_changing_their_role(): void
    {
        [$workspace, $token, $invitation, $invitee] = $this->pendingInvitation();
        $this->member($workspace, $invitee, WorkspaceRole::Auditor);
        $this->authenticate($invitee);

        $this->postJson("/api/v1/invitations/{$token}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('errors.invitation.0', 'The invitation is invalid or has expired.');

        $this->assertDatabaseCount('workspace_user', 2);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $invitee->id,
        ]);
        $this->assertSame(WorkspaceRole::Auditor, app(WorkspacePermissions::class)->role($invitee, $workspace));
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_expired_invitation_is_rejected_and_existing_current_workspace_is_preserved(): void
    {
        [$workspace, $token, $invitation, $invitee] = $this->pendingInvitation();
        $current = $this->workspace($invitee);
        $invitee->forceFill(['current_workspace_id' => $current->id])->save();
        $invitation->update(['expires_at' => now()->subSecond()]);
        $this->authenticate($invitee);
        $this->postJson("/api/v1/invitations/{$token}/accept")->assertUnprocessable();
        $this->assertSame($current->id, $invitee->fresh()->current_workspace_id);

        $invitation->update(['expires_at' => now()->addDay()]);
        $this->postJson("/api/v1/invitations/{$token}/accept")->assertOk();
        $this->assertSame($current->id, $invitee->fresh()->current_workspace_id);
    }

    public function test_invitation_acceptance_is_atomic(): void
    {
        [$workspace, $token, $invitation, $invitee] = $this->pendingInvitation();
        WorkspaceMembership::creating(static fn () => throw new RuntimeException('fail'));
        $this->authenticate($invitee);
        $this->withoutExceptionHandling();
        try {
            $this->postJson("/api/v1/invitations/{$token}/accept");
        } catch (RuntimeException) {
            $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $invitee->id]);
            $this->assertNull($invitation->fresh()->accepted_at);
        } finally {
            WorkspaceMembership::flushEventListeners();
        }
    }

    public function test_role_management_enforces_actor_and_owner_rules_and_stays_scoped(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $workspace = $this->workspace($owner);
        $other = $this->workspace($otherOwner);
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $this->member($workspace, $admin, WorkspaceRole::Admin);
        $this->member($workspace, $member, WorkspaceRole::Requester);
        $this->member($other, $member, WorkspaceRole::Auditor);

        $this->authenticate($admin);
        $url = "/api/v1/workspaces/{$workspace->id}/members/{$member->id}/role";
        $this->patchJson($url, ['role' => 'admin'])->assertUnprocessable();
        $this->patchJson($url, ['role' => 'approver'])->assertOk();
        $this->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$admin->id}/role", ['role' => 'auditor'])->assertUnprocessable();

        $this->authenticate($owner);
        $this->patchJson($url, ['role' => 'admin'])->assertOk();
        $this->patchJson($url, ['role' => 'owner'])->assertUnprocessable();
        $this->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$owner->id}/role", ['role' => 'requester'])->assertUnprocessable();
        $this->assertSame(WorkspaceRole::Admin, app(WorkspacePermissions::class)->role($member, $workspace));
        $this->assertSame(WorkspaceRole::Auditor, app(WorkspacePermissions::class)->role($member, $other));

        $requester = User::factory()->create();
        $this->member($workspace, $requester, WorkspaceRole::Requester);
        $this->authenticate($requester);
        $this->patchJson($url, ['role' => 'auditor'])->assertForbidden();
    }

    public function test_invitation_endpoints_require_authentication(): void
    {
        $workspace = Workspace::factory()->create();
        $this->getJson("/api/v1/workspaces/{$workspace->id}/invitations")->assertUnauthorized();
        $this->postJson('/api/v1/invitations/token/accept')->assertUnauthorized();
    }

    /** @return array{Workspace, string, WorkspaceInvitation, User} */
    private function pendingInvitation(): array
    {
        Notification::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'accept@example.com']);
        $workspace = $this->workspace($owner);
        $this->authenticate($owner);
        $data = $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", ['email' => $invitee->email, 'role' => 'approver'])->json('data');

        return [$workspace, $data['token'], WorkspaceInvitation::query()->findOrFail($data['id']), $invitee];
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

    private function authenticate(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }
}

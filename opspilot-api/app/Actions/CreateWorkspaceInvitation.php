<?php

namespace App\Actions;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateWorkspaceInvitation
{
    /** @return array{invitation: WorkspaceInvitation, token: string} */
    public function create(Workspace $workspace, User $inviter, string $email, WorkspaceRole $role): array
    {
        return DB::transaction(function () use ($workspace, $inviter, $email, $role): array {
            Workspace::query()->lockForUpdate()->findOrFail($workspace->id);

            if ($workspace->members()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email' => 'This user is already a workspace member.']);
            }

            if ($workspace->invitations()->where('email', $email)->whereNull('accepted_at')
                ->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['email' => 'An active invitation already exists for this email.']);
            }

            $token = Str::random(64);
            $invitation = $workspace->invitations()->create([
                'invited_by' => $inviter->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(config('workspaces.invitation_lifetime_days')),
            ]);

            $this->notify($invitation, $token);

            return compact('invitation', 'token');
        });
    }

    public function resend(WorkspaceInvitation $invitation): string
    {
        return DB::transaction(function () use ($invitation): string {
            $locked = WorkspaceInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if (! $locked->isPending()) {
                throw ValidationException::withMessages(['invitation' => 'The invitation is not pending.']);
            }

            $token = Str::random(64);
            $locked->update([
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(config('workspaces.invitation_lifetime_days')),
            ]);
            $this->notify($locked, $token);

            return $token;
        });
    }

    public function revoke(WorkspaceInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $locked = WorkspaceInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if (! $locked->isPending()) {
                throw ValidationException::withMessages(['invitation' => 'The invitation is not pending.']);
            }

            $locked->update(['revoked_at' => now()]);
        });
    }

    private function notify(WorkspaceInvitation $invitation, string $token): void
    {
        Notification::route('mail', $invitation->email)
            ->notify((new WorkspaceInvitationNotification($invitation, $token))->afterCommit());
    }
}

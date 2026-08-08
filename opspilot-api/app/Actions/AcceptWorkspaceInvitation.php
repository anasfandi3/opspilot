<?php

namespace App\Actions;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptWorkspaceInvitation
{
    public function __construct(
        private SynchronizeWorkspacePermissions $synchronize,
        private WorkspacePermissions $permissions,
    ) {}

    public function handle(User $user, string $token): WorkspaceInvitation
    {
        return DB::transaction(function () use ($user, $token): WorkspaceInvitation {
            $invitation = WorkspaceInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()->first();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $invitation?->isPending()) {
                $this->invalid();
            }

            if (mb_strtolower($lockedUser->email) !== $invitation->email) {
                $this->invalid();
            }

            $workspace = $invitation->workspace()->lockForUpdate()->firstOrFail();
            $membership = WorkspaceMembership::query()
                ->whereBelongsTo($workspace)
                ->whereBelongsTo($lockedUser)
                ->lockForUpdate()
                ->first();

            if ($membership !== null) {
                $this->invalid();
            }

            $this->synchronize->handle($workspace);
            WorkspaceMembership::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $lockedUser->id,
                'joined_at' => now(),
            ]);
            $this->permissions->assign($lockedUser, $workspace, $invitation->role);
            $invitation->update(['accepted_at' => now()]);

            if ($lockedUser->current_workspace_id === null) {
                $lockedUser->forceFill(['current_workspace_id' => $workspace->id])->save();
            }

            return $invitation->refresh();
        });
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['invitation' => 'The invitation is invalid or has expired.']);
    }
}

<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;

class RemoveWorkspaceMember
{
    public function handle(Workspace $workspace, User $user): void
    {
        DB::transaction(function () use ($workspace, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            WorkspaceMembership::query()
                ->whereBelongsTo($workspace)
                ->whereBelongsTo($lockedUser)
                ->firstOrFail()
                ->delete();

            if ($lockedUser->current_workspace_id !== $workspace->id) {
                return;
            }

            $nextWorkspaceId = WorkspaceMembership::query()
                ->whereBelongsTo($lockedUser)
                ->oldest('joined_at')
                ->oldest('id')
                ->value('workspace_id');

            $lockedUser->forceFill(['current_workspace_id' => $nextWorkspaceId])->save();
        });
    }
}

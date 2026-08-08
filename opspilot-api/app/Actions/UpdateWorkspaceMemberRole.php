<?php

namespace App\Actions;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateWorkspaceMemberRole
{
    public function __construct(private WorkspacePermissions $permissions) {}

    public function handle(Workspace $workspace, User $actor, User $member, WorkspaceRole $role): void
    {
        DB::transaction(function () use ($workspace, $actor, $member, $role): void {
            $membership = WorkspaceMembership::query()->whereBelongsTo($workspace)
                ->whereBelongsTo($member)->lockForUpdate()->first();
            if (! $membership) {
                throw ValidationException::withMessages(['user' => 'The user is not a workspace member.']);
            }
            if ($actor->is($member)) {
                throw ValidationException::withMessages(['user' => 'You cannot change your own role.']);
            }
            if ($workspace->owner_id === $member->id) {
                throw ValidationException::withMessages(['user' => "The owner's role cannot be changed."]);
            }

            $this->permissions->assign($member, $workspace, $role);
        });
    }
}

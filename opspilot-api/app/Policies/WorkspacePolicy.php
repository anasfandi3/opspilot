<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->membershipFor($user) !== null;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return in_array($workspace->roleFor($user), [WorkspaceRole::Owner, WorkspaceRole::Admin], true);
    }

    public function switchTo(User $user, Workspace $workspace): bool
    {
        return $this->view($user, $workspace);
    }

    public function leave(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id !== $user->id && $this->view($user, $workspace);
    }

    public function viewMembers(User $user, Workspace $workspace): bool
    {
        return $this->view($user, $workspace);
    }

    public function removeMember(User $user, Workspace $workspace, User $member): bool
    {
        $role = $workspace->roleFor($user);

        return in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true)
            && $member->id !== $user->id
            && $member->id !== $workspace->owner_id
            && $workspace->membershipFor($member) !== null;
    }
}

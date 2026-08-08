<?php

namespace App\Policies;

use App\Enums\WorkspacePermission;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspacePermissions;

class WorkspacePolicy
{
    public function __construct(private WorkspacePermissions $permissions) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->membershipFor($user) !== null
            && $this->permissions->allows($user, $workspace, WorkspacePermission::WorkspaceView);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->permissions->allows($user, $workspace, WorkspacePermission::WorkspaceUpdate);
    }

    public function switchTo(User $user, Workspace $workspace): bool
    {
        return $this->view($user, $workspace);
    }

    public function leave(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id !== $user->id && $workspace->membershipFor($user) !== null;
    }

    public function viewMembers(User $user, Workspace $workspace): bool
    {
        return $this->permissions->allows($user, $workspace, WorkspacePermission::MembersView);
    }

    public function manageInvitations(User $user, Workspace $workspace): bool
    {
        return $this->permissions->allows($user, $workspace, WorkspacePermission::InvitationsView);
    }

    public function createInvitation(User $user, Workspace $workspace): bool
    {
        return $this->permissions->allows($user, $workspace, WorkspacePermission::InvitationsCreate);
    }

    public function revokeInvitation(User $user, Workspace $workspace): bool
    {
        return $this->permissions->allows($user, $workspace, WorkspacePermission::InvitationsRevoke);
    }

    public function assignRoles(User $user, Workspace $workspace): bool
    {
        return $this->permissions->allows($user, $workspace, WorkspacePermission::MembersAssignRoles);
    }

    public function viewRequestTypes(User $user, Workspace $workspace): bool
    {
        return $workspace->membershipFor($user) !== null
            && $this->permissions->allows($user, $workspace, WorkspacePermission::RequestTypesView);
    }

    public function manageRequestTypes(User $user, Workspace $workspace): bool
    {
        return $workspace->membershipFor($user) !== null
            && $this->permissions->allows($user, $workspace, WorkspacePermission::RequestTypesManage);
    }

    public function viewWorkflows(User $user, Workspace $workspace): bool
    {
        return $workspace->membershipFor($user) !== null
            && $this->permissions->allows($user, $workspace, WorkspacePermission::WorkflowsView);
    }

    public function manageWorkflows(User $user, Workspace $workspace): bool
    {
        return $workspace->membershipFor($user) !== null
            && $this->permissions->allows($user, $workspace, WorkspacePermission::WorkflowsManage);
    }

    public function removeMember(User $user, Workspace $workspace, User $member): bool
    {
        return $this->permissions->allows($user, $workspace, WorkspacePermission::MembersManage)
            && $member->id !== $user->id && $member->id !== $workspace->owner_id
            && $workspace->membershipFor($member) !== null;
    }
}

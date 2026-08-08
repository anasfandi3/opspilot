<?php

namespace App\Support;

use App\Enums\WorkspacePermission;
use App\Models\RequestApproval;
use App\Models\User;

class RequestApprovalAccess
{
    public function __construct(private WorkspacePermissions $permissions) {}

    public function canView(User $user, RequestApproval $approval): bool
    {
        return $this->assignedMemberWithPermission($user, $approval, WorkspacePermission::ApprovalsViewAssigned);
    }

    public function canAct(User $user, RequestApproval $approval): bool
    {
        return $this->assignedMemberWithPermission($user, $approval, WorkspacePermission::ApprovalsAct);
    }

    private function assignedMemberWithPermission(User $user, RequestApproval $approval, WorkspacePermission $permission): bool
    {
        $workspace = $approval->relationLoaded('workspace') ? $approval->workspace : $approval->workspace()->firstOrFail();

        return $workspace->membershipFor($user) !== null
            && $approval->assignees()->whereBelongsTo($user)->exists()
            && $this->permissions->allows($user, $workspace, $permission);
    }
}

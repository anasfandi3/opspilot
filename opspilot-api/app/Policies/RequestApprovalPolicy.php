<?php

namespace App\Policies;

use App\Enums\RequestApprovalStatus;
use App\Enums\RequestStatus;
use App\Enums\WorkspacePermission;
use App\Models\RequestApproval;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RequestApprovalAccess;
use App\Support\WorkspacePermissions;

class RequestApprovalPolicy
{
    public function __construct(
        private WorkspacePermissions $permissions,
        private RequestApprovalAccess $access,
    ) {}

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->membershipFor($user) !== null
            && $this->permissions->allows($user, $workspace, WorkspacePermission::ApprovalsViewAssigned);
    }

    public function view(User $user, RequestApproval $approval): bool
    {
        return $this->access->canView($user, $approval);
    }

    public function approve(User $user, RequestApproval $approval): bool
    {
        return $this->actionableBy($user, $approval);
    }

    public function reject(User $user, RequestApproval $approval): bool
    {
        return $this->actionableBy($user, $approval);
    }

    private function actionableBy(User $user, RequestApproval $approval): bool
    {
        return $approval->status === RequestApprovalStatus::Pending
            && $approval->requestSubmission()->where('status', RequestStatus::Submitted)->exists()
            && $this->access->canAct($user, $approval);
    }
}

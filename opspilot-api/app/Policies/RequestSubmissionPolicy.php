<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Enums\WorkspacePermission;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspacePermissions;

class RequestSubmissionPolicy
{
    public function __construct(private WorkspacePermissions $permissions) {}

    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->membershipFor($user) !== null
            && $this->permissions->allows($user, $workspace, WorkspacePermission::RequestsCreate);
    }

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->membershipFor($user) !== null
            && ($this->viewAll($user, $workspace)
                || $this->permissions->allows($user, $workspace, WorkspacePermission::RequestsViewOwn));
    }

    public function viewAll(User $user, Workspace $workspace): bool
    {
        return $workspace->membershipFor($user) !== null
            && $this->permissions->allows($user, $workspace, WorkspacePermission::RequestsViewAll);
    }

    public function view(User $user, RequestSubmission $submission): bool
    {
        return $submission->workspace->membershipFor($user) !== null
            && ($this->viewAll($user, $submission->workspace)
                || ($submission->created_by === $user->id
                    && $this->permissions->allows($user, $submission->workspace, WorkspacePermission::RequestsViewOwn))
                || ($this->permissions->allows($user, $submission->workspace, WorkspacePermission::ApprovalsViewAssigned)
                    && $submission->approvals()->whereHas(
                        'assignees',
                        fn ($query) => $query->whereBelongsTo($user),
                    )->exists()));
    }

    public function update(User $user, RequestSubmission $submission): bool
    {
        return $submission->created_by === $user->id
            && $submission->status === RequestStatus::Draft
            && $this->permissions->allows($user, $submission->workspace, WorkspacePermission::RequestsUpdateOwn);
    }

    public function submit(User $user, RequestSubmission $submission): bool
    {
        return $submission->created_by === $user->id
            && $submission->status === RequestStatus::Draft
            && $this->permissions->allows($user, $submission->workspace, WorkspacePermission::RequestsSubmit);
    }

    public function cancel(User $user, RequestSubmission $submission): bool
    {
        return $submission->created_by === $user->id
            && in_array($submission->status, [RequestStatus::Draft, RequestStatus::Submitted], true)
            && $this->permissions->allows($user, $submission->workspace, WorkspacePermission::RequestsCancelOwn);
    }
}

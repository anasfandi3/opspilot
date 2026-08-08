<?php

namespace App\Support;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\RequestApproval;
use App\Models\RequestApprovalAssignee;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\RequestSubmissionPolicy;
use Illuminate\Database\Eloquent\Collection;

class RequestNotificationRecipients
{
    public function __construct(
        private WorkspacePermissions $permissions,
        private RequestSubmissionPolicy $submissions,
    ) {}

    /** @return Collection<int, User> */
    public function approvalAssignees(RequestApproval $approval, ?User $exclude = null): Collection
    {
        $workspace = $approval->relationLoaded('workspace') ? $approval->workspace : $approval->workspace()->firstOrFail();

        return User::query()
            ->whereHas('approvalAssignments', fn ($query) => $query->whereBelongsTo($approval, 'approval'))
            ->whereHas('workspaceMemberships', fn ($query) => $query->whereBelongsTo($workspace))
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => $user->id !== $exclude?->id
                && $this->permissions->allows($user, $workspace, WorkspacePermission::ApprovalsViewAssigned)
                && $this->permissions->allows($user, $workspace, WorkspacePermission::ApprovalsAct))
            ->values();
    }

    public function requestCreator(RequestSubmission $submission): ?User
    {
        $creator = $submission->creator()->first();

        return $creator && $this->submissions->view($creator, $submission) ? $creator : null;
    }

    /** @return Collection<int, User> */
    public function collaborationParticipants(RequestSubmission $submission, User $exclude): Collection
    {
        $workspace = $submission->relationLoaded('workspace')
            ? $submission->workspace
            : $submission->workspace()->firstOrFail();
        $candidateIds = collect([$submission->created_by])
            ->merge(RequestApprovalAssignee::query()
                ->whereHas('approval', fn ($query) => $query->whereBelongsTo($submission, 'requestSubmission'))
                ->pluck('user_id'))
            ->merge($this->managerIds($workspace))
            ->unique()
            ->reject(fn (int $id): bool => $id === $exclude->id);

        return User::query()
            ->whereIn('id', $candidateIds)
            ->whereHas('workspaceMemberships', fn ($query) => $query->whereBelongsTo($workspace))
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => $this->submissions->view($user, $submission))
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function managerIds(Workspace $workspace): \Illuminate\Support\Collection
    {
        return $this->permissions->within($workspace, fn () => User::query()
            ->whereHas('workspaceMemberships', fn ($query) => $query->whereBelongsTo($workspace))
            ->role([WorkspaceRole::Owner->value, WorkspaceRole::Admin->value])
            ->pluck('id'));
    }
}

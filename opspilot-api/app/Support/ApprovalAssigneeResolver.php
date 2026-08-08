<?php

namespace App\Support;

use App\Enums\WorkflowApproverType;
use App\Enums\WorkspacePermission;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ApprovalAssigneeResolver
{
    public function __construct(private WorkspacePermissions $permissions) {}

    /** @return Collection<int, User> */
    public function resolve(WorkflowStep $step, Workspace $workspace): Collection
    {
        if ($step->approver_type === WorkflowApproverType::User) {
            return $this->specificUser($step, $workspace);
        }

        return $this->roleUsers($step, $workspace);
    }

    /** @return Collection<int, User> */
    private function specificUser(WorkflowStep $step, Workspace $workspace): Collection
    {
        $user = User::query()->find($step->approver_user_id);
        if (! $user || $workspace->membershipFor($user) === null
            || ! $this->permissions->allows($user, $workspace, WorkspacePermission::ApprovalsAct)) {
            $this->fail($step);
        }

        return new Collection([$user]);
    }

    /** @return Collection<int, User> */
    private function roleUsers(WorkflowStep $step, Workspace $workspace): Collection
    {
        $role = $step->approver_role;
        if ($role === null) {
            $this->fail($step);
        }

        $users = $this->permissions->within($workspace, function () use ($workspace, $role): Collection {
            $memberIds = $workspace->members()->select((new User)->qualifyColumn('id'));

            return User::query()
                ->whereIn((new User)->qualifyColumn('id'), $memberIds)
                ->role($role->value)
                ->permission(WorkspacePermission::ApprovalsAct->value)
                ->orderBy('id')
                ->get();
        });

        if ($users->isEmpty()) {
            $this->fail($step);
        }

        return $users;
    }

    private function fail(WorkflowStep $step): never
    {
        throw ValidationException::withMessages([
            'workflow' => "Workflow step {$step->position} has no eligible approvers.",
        ]);
    }
}

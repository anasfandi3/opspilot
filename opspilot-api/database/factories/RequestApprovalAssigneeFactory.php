<?php

namespace Database\Factories;

use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\WorkflowApproverType;
use App\Enums\WorkspaceRole;
use App\Models\RequestApproval;
use App\Models\RequestApprovalAssignee;
use App\Models\User;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestApprovalAssignee>
 */
class RequestApprovalAssigneeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_approval_id' => RequestApproval::factory()->pending(),
            'user_id' => function (array $attributes): int {
                $approval = RequestApproval::query()->findOrFail($attributes['request_approval_id']);
                $step = $approval->workflowStep;
                $user = $step->approver_type === WorkflowApproverType::User
                    ? $step->approverUser
                    : User::factory()->create();
                $role = $step->approver_type === WorkflowApproverType::Role
                    ? $step->approver_role
                    : WorkspaceRole::Approver;

                WorkspaceMembership::query()->firstOrCreate(
                    ['workspace_id' => $approval->workspace_id, 'user_id' => $user->id],
                    ['joined_at' => now()],
                );
                app(SynchronizeWorkspacePermissions::class)->handle($approval->workspace);
                app(WorkspacePermissions::class)->assign($user, $approval->workspace, $role);

                return $user->id;
            },
        ];
    }
}

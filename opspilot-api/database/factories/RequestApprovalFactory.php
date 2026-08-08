<?php

namespace Database\Factories;

use App\Enums\RequestApprovalStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestApproval;
use App\Models\RequestSubmission;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestApproval>
 */
class RequestApprovalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_submission_id' => RequestSubmission::factory()->submitted(),
            'workspace_id' => fn (array $attributes): int => RequestSubmission::query()->findOrFail($attributes['request_submission_id'])->workspace_id,
            'workflow_step_id' => function (array $attributes): int {
                $submission = RequestSubmission::query()->findOrFail($attributes['request_submission_id']);

                return WorkflowStep::factory()->create([
                    'workflow_id' => $submission->workflow_id,
                    'approver_role' => WorkspaceRole::Owner,
                ])->id;
            },
            'position' => 1,
            'status' => RequestApprovalStatus::Waiting,
            'pending_guard' => null,
            'activated_at' => null,
            'decided_at' => null,
            'decided_by' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => RequestApprovalStatus::Pending,
            'pending_guard' => 1,
            'activated_at' => now(),
        ]);
    }
}

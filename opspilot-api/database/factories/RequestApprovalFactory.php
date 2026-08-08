<?php

namespace Database\Factories;

use App\Enums\RequestApprovalStatus;
use App\Models\RequestApproval;
use App\Models\RequestSubmission;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestApproval>
 */
class RequestApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'request_submission_id' => RequestSubmission::factory(),
            'workflow_step_id' => WorkflowStep::factory(),
            'position' => 1,
            'status' => RequestApprovalStatus::Waiting,
            'pending_guard' => null,
            'activated_at' => null,
            'decided_at' => null,
            'decided_by' => null,
        ];
    }
}

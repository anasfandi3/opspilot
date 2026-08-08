<?php

namespace Database\Factories;

use App\Enums\WorkflowApproverType;
use App\Enums\WorkflowConditionLogic;
use App\Enums\WorkspaceRole;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowStepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'name' => fake()->words(2, true),
            'position' => 1,
            'approver_type' => WorkflowApproverType::Role,
            'approver_role' => WorkspaceRole::Approver,
            'approver_user_id' => null,
            'condition_logic' => WorkflowConditionLogic::All,
        ];
    }
}

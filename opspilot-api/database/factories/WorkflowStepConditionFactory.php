<?php

namespace Database\Factories;

use App\Enums\WorkflowConditionOperator;
use App\Models\RequestTypeField;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowStepConditionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_step_id' => WorkflowStep::factory(),
            'request_type_field_id' => RequestTypeField::factory(),
            'operator' => WorkflowConditionOperator::Equals,
            'value' => 'value',
            'position' => 1,
        ];
    }
}

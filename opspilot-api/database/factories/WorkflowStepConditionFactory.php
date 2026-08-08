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
            'request_type_field_id' => function (array $attributes): int {
                $step = WorkflowStep::query()->findOrFail($attributes['workflow_step_id']);

                return RequestTypeField::factory()->create([
                    'request_type_id' => $step->workflow()->valueOrFail('request_type_id'),
                ])->id;
            },
            'operator' => WorkflowConditionOperator::Equals,
            'value' => 'value',
            'position' => 1,
        ];
    }
}

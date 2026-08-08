<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RequestFieldType;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use App\Support\WorkflowConditionEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowConditionEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_condition_set_always_applies(): void
    {
        [$step] = $this->definition();
        $step->forceFill(['condition_logic' => 'any'])->save();

        $this->assertTrue(app(WorkflowConditionEvaluator::class)->applies($step, []));
    }

    public function test_all_and_any_logic_are_evaluated_across_conditions(): void
    {
        [$step, $type] = $this->definition();
        $amount = $this->field($type, 'amount', RequestFieldType::Number);
        $priority = $this->field($type, 'priority', RequestFieldType::Text);
        $step->conditions()->create([
            'request_type_field_id' => $amount->id,
            'operator' => 'greater_than_or_equal',
            'value' => 100,
            'position' => 1,
        ]);
        $step->conditions()->create([
            'request_type_field_id' => $priority->id,
            'operator' => 'equals',
            'value' => 'high',
            'position' => 2,
        ]);
        $evaluator = app(WorkflowConditionEvaluator::class);

        $this->assertTrue($evaluator->applies($step->refresh(), ['amount' => 150, 'priority' => 'high']));
        $this->assertFalse($evaluator->applies($step->refresh(), ['amount' => 150, 'priority' => 'low']));
        $step->forceFill(['condition_logic' => 'any'])->save();
        $this->assertTrue($evaluator->applies($step->refresh(), ['amount' => 150, 'priority' => 'low']));
        $this->assertFalse($evaluator->applies($step->refresh(), ['amount' => 50, 'priority' => 'low']));
    }

    public function test_missing_and_null_values_are_false_even_for_negative_operators(): void
    {
        [$step, $type] = $this->definition();
        $field = $this->field($type, 'title', RequestFieldType::Text);
        $step->conditions()->create([
            'request_type_field_id' => $field->id,
            'operator' => 'not_equals',
            'value' => 'blocked',
            'position' => 1,
        ]);
        $evaluator = app(WorkflowConditionEvaluator::class);

        $this->assertFalse($evaluator->applies($step->refresh(), []));
        $this->assertFalse($evaluator->applies($step->refresh(), ['title' => null]));
    }

    public function test_supported_field_operators_use_strict_typed_semantics(): void
    {
        $cases = [
            [RequestFieldType::Number, 'equals', 5, 5, true],
            [RequestFieldType::Number, 'equals', 5, '5', false],
            [RequestFieldType::Number, 'greater_than', 5, 6, true],
            [RequestFieldType::Decimal, 'less_than_or_equal', 5.5, 5.25, true],
            [RequestFieldType::Boolean, 'equals', true, true, true],
            [RequestFieldType::Boolean, 'not_equals', true, false, true],
            [RequestFieldType::Boolean, 'equals', true, 1, false],
            [RequestFieldType::Text, 'equals', 'high', 'high', true],
            [RequestFieldType::Textarea, 'not_equals', 'blocked', 'open', true],
            [RequestFieldType::Email, 'in', ['a@example.com', 'b@example.com'], 'b@example.com', true],
            [RequestFieldType::Url, 'not_in', ['https://blocked.test'], 'https://allowed.test', true],
            [RequestFieldType::Select, 'in', ['high', 'urgent'], 'high', true],
            [RequestFieldType::Multiselect, 'contains', 'finance', ['finance', 'operations'], true],
            [RequestFieldType::Multiselect, 'not_contains', 'legal', ['finance'], true],
            [RequestFieldType::Date, 'greater_than', '2026-08-01', '2026-08-08', true],
            [RequestFieldType::Datetime, 'equals', '2026-08-08T12:00:00+00:00', '2026-08-08T14:00:00+02:00', true],
        ];

        foreach ($cases as $index => [$fieldType, $operator, $expected, $actual, $result]) {
            [$step, $type] = $this->definition();
            $key = 'field_'.$index;
            $field = $this->field($type, $key, $fieldType);
            $step->conditions()->create([
                'request_type_field_id' => $field->id,
                'operator' => $operator,
                'value' => $expected,
                'position' => 1,
            ]);

            $this->assertSame(
                $result,
                app(WorkflowConditionEvaluator::class)->applies($step->refresh(), [$key => $actual]),
                "Failed condition case {$index}.",
            );
        }
    }

    /** @return array{WorkflowStep, RequestType} */
    private function definition(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user]);
        $type = RequestType::factory()->create(['workspace_id' => $workspace, 'created_by' => $user]);
        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $type,
            'created_by' => $user,
        ]);
        $step = WorkflowStep::factory()->create(['workflow_id' => $workflow]);

        return [$step, $type];
    }

    private function field(RequestType $type, string $key, RequestFieldType $fieldType): RequestTypeField
    {
        return RequestTypeField::factory()->create([
            'request_type_id' => $type,
            'key' => $key,
            'type' => $fieldType,
        ]);
    }
}

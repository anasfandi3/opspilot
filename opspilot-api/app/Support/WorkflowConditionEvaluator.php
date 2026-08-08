<?php

namespace App\Support;

use App\Enums\RequestFieldType;
use App\Enums\WorkflowConditionLogic;
use App\Enums\WorkflowConditionOperator;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepCondition;
use DateTimeImmutable;

class WorkflowConditionEvaluator
{
    /** @param array<string, mixed> $payload */
    public function applies(WorkflowStep $step, array $payload): bool
    {
        $conditions = $step->relationLoaded('conditions')
            ? $step->conditions
            : $step->conditions()->with('requestTypeField')->get();

        if ($conditions->isEmpty()) {
            return true;
        }

        $results = $conditions->map(fn (WorkflowStepCondition $condition): bool => $this->matches($condition, $payload));

        return $step->condition_logic === WorkflowConditionLogic::All
            ? $results->every(static fn (bool $result): bool => $result)
            : $results->contains(true);
    }

    /** @param array<string, mixed> $payload */
    private function matches(WorkflowStepCondition $condition, array $payload): bool
    {
        $field = $condition->requestTypeField;
        if (! array_key_exists($field->key, $payload) || $payload[$field->key] === null) {
            return false;
        }

        $actual = $payload[$field->key];
        $expected = $condition->value;

        return match ($field->type) {
            RequestFieldType::Number, RequestFieldType::Decimal => $this->matchesNumeric($condition->operator, $actual, $expected),
            RequestFieldType::Boolean => $this->matchesBoolean($condition->operator, $actual, $expected),
            RequestFieldType::Text, RequestFieldType::Textarea, RequestFieldType::Email,
            RequestFieldType::Url, RequestFieldType::Select => $this->matchesString($condition->operator, $actual, $expected),
            RequestFieldType::Multiselect => $this->matchesMultiselect($condition->operator, $actual, $expected),
            RequestFieldType::Date => $this->matchesTemporal($condition->operator, $actual, $expected, 'Y-m-d'),
            RequestFieldType::Datetime => $this->matchesTemporal($condition->operator, $actual, $expected, 'Y-m-d\TH:i:sP'),
        };
    }

    private function matchesNumeric(WorkflowConditionOperator $operator, mixed $actual, mixed $expected): bool
    {
        if ((! is_int($actual) && ! is_float($actual)) || (! is_int($expected) && ! is_float($expected))) {
            return false;
        }

        return $this->matchesComparison($operator, $actual <=> $expected);
    }

    private function matchesBoolean(WorkflowConditionOperator $operator, mixed $actual, mixed $expected): bool
    {
        if (! is_bool($actual) || ! is_bool($expected)) {
            return false;
        }

        return match ($operator) {
            WorkflowConditionOperator::Equals => $actual === $expected,
            WorkflowConditionOperator::NotEquals => $actual !== $expected,
            default => false,
        };
    }

    private function matchesString(WorkflowConditionOperator $operator, mixed $actual, mixed $expected): bool
    {
        if (! is_string($actual)) {
            return false;
        }

        return match ($operator) {
            WorkflowConditionOperator::Equals => is_string($expected) && $actual === $expected,
            WorkflowConditionOperator::NotEquals => is_string($expected) && $actual !== $expected,
            WorkflowConditionOperator::In => is_array($expected) && in_array($actual, $expected, true),
            WorkflowConditionOperator::NotIn => is_array($expected) && ! in_array($actual, $expected, true),
            default => false,
        };
    }

    private function matchesMultiselect(WorkflowConditionOperator $operator, mixed $actual, mixed $expected): bool
    {
        if (! is_array($actual) || is_array($expected)) {
            return false;
        }

        return match ($operator) {
            WorkflowConditionOperator::Contains => in_array($expected, $actual, true),
            WorkflowConditionOperator::NotContains => ! in_array($expected, $actual, true),
            default => false,
        };
    }

    private function matchesTemporal(WorkflowConditionOperator $operator, mixed $actual, mixed $expected, string $format): bool
    {
        if (! is_string($actual) || ! is_string($expected)) {
            return false;
        }

        $parseFormat = $format === 'Y-m-d' ? '!Y-m-d' : $format;
        $actualDate = DateTimeImmutable::createFromFormat($parseFormat, $actual);
        $expectedDate = DateTimeImmutable::createFromFormat($parseFormat, $expected);
        if (! $actualDate || ! $expectedDate || $actualDate->format($format) !== $actual || $expectedDate->format($format) !== $expected) {
            return false;
        }

        return $this->matchesComparison($operator, $actualDate->getTimestamp() <=> $expectedDate->getTimestamp());
    }

    private function matchesComparison(WorkflowConditionOperator $operator, int $comparison): bool
    {
        return match ($operator) {
            WorkflowConditionOperator::Equals => $comparison === 0,
            WorkflowConditionOperator::NotEquals => $comparison !== 0,
            WorkflowConditionOperator::GreaterThan => $comparison > 0,
            WorkflowConditionOperator::GreaterThanOrEqual => $comparison >= 0,
            WorkflowConditionOperator::LessThan => $comparison < 0,
            WorkflowConditionOperator::LessThanOrEqual => $comparison <= 0,
            default => false,
        };
    }
}

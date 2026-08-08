<?php

namespace App\Support;

use App\Enums\RequestFieldType;
use App\Enums\WorkflowApproverType;
use App\Enums\WorkflowConditionOperator;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\RequestTypeField;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepCondition;
use DateTimeImmutable;
use Illuminate\Validation\ValidationException;

class WorkflowDefinitionValidator
{
    public function __construct(private WorkspacePermissions $permissions) {}

    /** @param array<string, mixed> $data */
    public function validateStepData(Workflow $workflow, array $data, string $prefix = ''): void
    {
        $approverType = WorkflowApproverType::tryFrom((string) ($data['approver_type'] ?? ''));
        if ($approverType === WorkflowApproverType::Role) {
            $role = WorkspaceRole::tryFrom((string) ($data['approver_role'] ?? ''));
            if (! in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin, WorkspaceRole::Approver], true)) {
                $this->fail($prefix.'approver_role', 'The selected approver role cannot act on approvals.');
            }
            if (($data['approver_user_id'] ?? null) !== null) {
                $this->fail($prefix.'approver_user_id', 'A role approver may not specify a user.');
            }
        } elseif ($approverType === WorkflowApproverType::User) {
            if (($data['approver_role'] ?? null) !== null) {
                $this->fail($prefix.'approver_role', 'A user approver may not specify a role.');
            }
            $user = User::query()->find($data['approver_user_id'] ?? null);
            if (! $user || $workflow->workspace->membershipFor($user) === null
                || ! $this->permissions->allows($user, $workflow->workspace, WorkspacePermission::ApprovalsAct)) {
                $this->fail($prefix.'approver_user_id', 'The selected user is not an eligible workspace approver.');
            }
        } else {
            $this->fail($prefix.'approver_type', 'The approver type is invalid.');
        }

        foreach ($data['conditions'] ?? [] as $index => $condition) {
            $fieldId = $condition['field_id'] ?? null;
            $field = $workflow->requestType->relationLoaded('fields')
                ? $workflow->requestType->fields->firstWhere('id', $fieldId)
                : $workflow->requestType->fields()->find($fieldId);
            if (! $field) {
                $this->fail($prefix."conditions.{$index}.field_id", 'The selected field does not belong to this request type.');
            }
            $this->validateCondition(
                $field,
                (string) ($condition['operator'] ?? ''),
                $condition['value'] ?? null,
                $prefix."conditions.{$index}",
            );
        }
    }

    public function validateWorkflow(Workflow $workflow): void
    {
        $workflow->loadMissing(['workspace', 'requestType.fields', 'steps.conditions.requestTypeField']);
        if ($workflow->steps->isEmpty()) {
            $this->fail('workflow', 'A workflow must contain at least one step before publishing.');
        }

        foreach ($workflow->steps as $index => $step) {
            $this->validateStepData($workflow, $this->stepData($step), "steps.{$index}.");
        }
    }

    /** @param array<string, mixed>|null $proposedConfig */
    public function validateFieldConfig(RequestTypeField $field, ?array $proposedConfig): void
    {
        $candidate = clone $field;
        $candidate->config = $proposedConfig;
        $conditions = $field->workflowConditions()->get();

        foreach ($conditions as $condition) {
            $this->validateCondition(
                $candidate,
                $condition->operator->value,
                $condition->value,
                'config',
            );
        }
    }

    public function validateCondition(RequestTypeField $field, string $operatorValue, mixed $value, string $attribute): void
    {
        $operator = WorkflowConditionOperator::tryFrom($operatorValue);
        if (! $operator || ! in_array($operator, $this->operatorsFor($field->type), true)) {
            $this->fail($attribute.'.operator', 'The operator is not supported for this field type.');
        }

        if (in_array($operator, [WorkflowConditionOperator::In, WorkflowConditionOperator::NotIn], true)) {
            if (! is_array($value) || $value === [] || count($value) !== count(array_unique($value, SORT_REGULAR))) {
                $this->fail($attribute.'.value', 'The condition value must be a non-empty array of unique values.');
            }
            foreach ($value as $item) {
                $this->validateScalar($field, $item, $attribute.'.value');
            }

            return;
        }

        if (is_array($value)) {
            $this->fail($attribute.'.value', 'The condition value must be scalar.');
        }
        $this->validateScalar($field, $value, $attribute.'.value');
    }

    /** @return list<WorkflowConditionOperator> */
    private function operatorsFor(RequestFieldType $type): array
    {
        $equality = [WorkflowConditionOperator::Equals, WorkflowConditionOperator::NotEquals];
        $comparison = [...$equality, WorkflowConditionOperator::GreaterThan, WorkflowConditionOperator::GreaterThanOrEqual, WorkflowConditionOperator::LessThan, WorkflowConditionOperator::LessThanOrEqual];
        $sets = [...$equality, WorkflowConditionOperator::In, WorkflowConditionOperator::NotIn];

        return match ($type) {
            RequestFieldType::Number, RequestFieldType::Decimal, RequestFieldType::Date, RequestFieldType::Datetime => $comparison,
            RequestFieldType::Text, RequestFieldType::Textarea, RequestFieldType::Email, RequestFieldType::Url, RequestFieldType::Select => $sets,
            RequestFieldType::Multiselect => [WorkflowConditionOperator::Contains, WorkflowConditionOperator::NotContains],
            RequestFieldType::Boolean => $equality,
        };
    }

    private function validateScalar(RequestTypeField $field, mixed $value, string $attribute): void
    {
        if (in_array($field->type, [RequestFieldType::Number, RequestFieldType::Decimal], true)) {
            if (! is_int($value) && ! is_float($value)) {
                $this->fail($attribute, 'The condition value must be numeric.');
            }

            return;
        }
        if ($field->type === RequestFieldType::Boolean) {
            if (! is_bool($value)) {
                $this->fail($attribute, 'The condition value must be boolean.');
            }

            return;
        }
        if (in_array($field->type, [RequestFieldType::Select, RequestFieldType::Multiselect], true)) {
            if (! is_string($value) || ! in_array($value, $this->optionValues($field), true)) {
                $this->fail($attribute, 'The condition value must be one of the configured options.');
            }

            return;
        }
        if ($field->type === RequestFieldType::Date) {
            $valid = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
            if (! $valid || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
                $this->fail($attribute, 'The condition value must be a valid date in YYYY-MM-DD format.');
            }

            return;
        }
        if ($field->type === RequestFieldType::Datetime) {
            $datetime = is_string($value)
                ? DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $value)
                : false;
            if (! $datetime || $datetime->format('Y-m-d\TH:i:sP') !== $value) {
                $this->fail($attribute, 'The condition value must be a valid datetime.');
            }

            return;
        }
        if (! is_string($value)) {
            $this->fail($attribute, 'The condition value must be a string.');
        }
    }

    /** @return list<string> */
    private function optionValues(RequestTypeField $field): array
    {
        return collect($field->config['options'] ?? [])->pluck('value')->all();
    }

    /** @return array<string, mixed> */
    private function stepData(WorkflowStep $step): array
    {
        return [
            'approver_type' => $step->approver_type->value,
            'approver_role' => $step->approver_role?->value,
            'approver_user_id' => $step->approver_user_id,
            'conditions' => $step->conditions->map(fn (WorkflowStepCondition $condition): array => [
                'field_id' => $condition->request_type_field_id,
                'operator' => $condition->operator->value,
                'value' => $condition->value,
            ])->all(),
        ];
    }

    private function fail(string $attribute, string $message): never
    {
        throw ValidationException::withMessages([$attribute => $message]);
    }
}

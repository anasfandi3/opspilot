<?php

namespace App\Actions;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepCondition;
use App\Support\WorkflowDefinitionValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveWorkflowStep
{
    public function __construct(private WorkflowDefinitionValidator $validator) {}

    /** @param array<string, mixed> $data */
    public function create(Workflow $workflow, array $data): WorkflowStep
    {
        return DB::transaction(function () use ($workflow, $data): WorkflowStep {
            $lockedWorkflow = $this->lockedDraft($workflow);
            $stepData = $this->normalizeApprover($data);
            $this->validator->validateStepData($lockedWorkflow, $stepData);
            $step = new WorkflowStep($stepData);
            $step->forceFill(['position' => ((int) $lockedWorkflow->steps()->max('position')) + 1]);
            $step->workflow()->associate($lockedWorkflow);
            $step->save();
            $this->syncConditions($step, $stepData['conditions'] ?? []);

            return $step->load(['approverUser:id,name,email', 'conditions.requestTypeField']);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Workflow $workflow, WorkflowStep $step, array $data): WorkflowStep
    {
        return DB::transaction(function () use ($workflow, $step, $data): WorkflowStep {
            $lockedWorkflow = $this->lockedDraft($workflow);
            $lockedStep = $lockedWorkflow->steps()->with('conditions')->reorder()->lockForUpdate()->findOrFail($step->id);
            $effectiveData = $this->effectiveUpdateData($lockedStep, $data);
            $this->validator->validateStepData($lockedWorkflow, $effectiveData);
            $lockedStep->update([
                'name' => $effectiveData['name'],
                'approver_type' => $effectiveData['approver_type'],
                'approver_role' => $effectiveData['approver_role'],
                'approver_user_id' => $effectiveData['approver_user_id'],
                'condition_logic' => $effectiveData['condition_logic'],
            ]);
            if (array_key_exists('conditions', $data)) {
                $this->syncConditions($lockedStep, $effectiveData['conditions']);
            }

            return $lockedStep->load(['approverUser:id,name,email', 'conditions.requestTypeField']);
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function effectiveUpdateData(WorkflowStep $step, array $data): array
    {
        return $this->normalizeApprover([
            'name' => $data['name'] ?? $step->name,
            'approver_type' => $data['approver_type'] ?? $step->approver_type->value,
            'approver_role' => array_key_exists('approver_role', $data) ? $data['approver_role'] : $step->approver_role?->value,
            'approver_user_id' => array_key_exists('approver_user_id', $data) ? $data['approver_user_id'] : $step->approver_user_id,
            'condition_logic' => $data['condition_logic'] ?? $step->condition_logic->value,
            'conditions' => array_key_exists('conditions', $data)
                ? $data['conditions']
                : $step->conditions->map(fn (WorkflowStepCondition $condition): array => [
                    'field_id' => $condition->request_type_field_id,
                    'operator' => $condition->operator->value,
                    'value' => $condition->value,
                ])->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeApprover(array $data): array
    {
        if (($data['approver_type'] ?? null) === 'role') {
            $data['approver_user_id'] = null;
        } elseif (($data['approver_type'] ?? null) === 'user') {
            $data['approver_role'] = null;
        }

        return $data;
    }

    public function delete(Workflow $workflow, WorkflowStep $step): void
    {
        DB::transaction(function () use ($workflow, $step): void {
            $lockedWorkflow = $this->lockedDraft($workflow);
            $lockedWorkflow->steps()->reorder()->lockForUpdate()->findOrFail($step->id)->delete();
        }, attempts: 3);
    }

    private function lockedDraft(Workflow $workflow): Workflow
    {
        $locked = Workflow::query()->with(['workspace', 'requestType'])->lockForUpdate()->findOrFail($workflow->id);
        if (! $locked->isDraft()) {
            throw ValidationException::withMessages(['workflow' => 'Only draft workflows may be modified.']);
        }

        return $locked;
    }

    /** @param list<array{field_id: int, operator: string, value: mixed}> $conditions */
    private function syncConditions(WorkflowStep $step, array $conditions): void
    {
        $step->conditions()->delete();
        foreach ($conditions as $index => $condition) {
            $step->conditions()->create([
                'request_type_field_id' => $condition['field_id'],
                'operator' => $condition['operator'],
                'value' => $condition['value'],
                'position' => $index + 1,
            ]);
        }
    }
}

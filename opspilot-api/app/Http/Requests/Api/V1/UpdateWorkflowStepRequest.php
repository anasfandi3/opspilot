<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\WorkflowApproverType;
use App\Enums\WorkflowConditionLogic;
use App\Enums\WorkflowConditionOperator;
use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageWorkflows', $this->route('workspace'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'approver_type' => ['sometimes', Rule::enum(WorkflowApproverType::class)],
            'approver_role' => ['sometimes', 'nullable', Rule::enum(WorkspaceRole::class)->only([
                WorkspaceRole::Owner, WorkspaceRole::Admin, WorkspaceRole::Approver,
            ])],
            'approver_user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'condition_logic' => ['sometimes', Rule::enum(WorkflowConditionLogic::class)],
            'conditions' => ['sometimes', 'array'],
            'conditions.*' => ['array:field_id,operator,value'],
            'conditions.*.field_id' => ['required', 'integer'],
            'conditions.*.operator' => ['required', Rule::enum(WorkflowConditionOperator::class)],
            'conditions.*.value' => ['present'],
            'workflow_id' => ['prohibited'],
            'position' => ['prohibited'],
        ];
    }
}

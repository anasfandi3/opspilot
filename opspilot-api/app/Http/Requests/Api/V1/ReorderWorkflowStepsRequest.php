<?php

namespace App\Http\Requests\Api\V1;

use App\Models\WorkflowStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderWorkflowStepsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageWorkflows', $this->route('workspace'));
    }

    public function rules(): array
    {
        return [
            'step_ids' => ['required', 'array'],
            'step_ids.*' => [
                'required', 'integer', 'distinct:strict',
                Rule::exists((new WorkflowStep)->getTable(), 'id')->where('workflow_id', $this->route('workflow')?->id),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (is_array($this->input('step_ids'))
                && count($this->input('step_ids')) !== $this->route('workflow')->steps()->count()) {
                $validator->errors()->add('step_ids', 'The step_ids must contain the complete step set.');
            }
        }];
    }
}

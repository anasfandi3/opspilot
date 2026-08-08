<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('requestSubmission'));
    }

    public function rules(): array
    {
        return [
            'payload' => ['sometimes', 'array'],
            'workspace_id' => ['missing'],
            'request_type_id' => ['missing'],
            'workflow_id' => ['missing'],
            'created_by' => ['missing'],
            'status' => ['missing'],
            'definition_snapshot' => ['missing'],
            'submitted_at' => ['missing'],
            'cancelled_at' => ['missing'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RequestSubmission;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequestSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [RequestSubmission::class, $this->route('workspace')]);
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

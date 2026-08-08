<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageWorkflows', $this->route('workspace'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'workspace_id' => ['prohibited'],
            'request_type_id' => ['prohibited'],
            'version' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'published_at' => ['prohibited'],
        ];
    }
}

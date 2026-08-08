<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRequestTypes', $this->route('workspace'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'workspace_id' => ['prohibited'],
            'slug' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }
}

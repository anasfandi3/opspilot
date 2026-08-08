<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RequestFieldType;
use App\Rules\RequestFieldConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestTypeFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRequestTypes', $this->route('workspace'));
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_required' => ['sometimes', 'boolean'],
            'config' => [
                'sometimes',
                Rule::requiredIf(in_array($this->route('field')?->type, [RequestFieldType::Select, RequestFieldType::Multiselect], true)),
                'nullable', 'array', new RequestFieldConfig($this->route('field')?->type),
            ],
            'key' => ['prohibited'],
            'type' => ['prohibited'],
            'request_type_id' => ['prohibited'],
            'position' => ['prohibited'],
        ];
    }
}

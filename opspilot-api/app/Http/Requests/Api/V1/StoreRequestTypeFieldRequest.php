<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RequestFieldType;
use App\Models\RequestTypeField;
use App\Rules\RequestFieldConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestTypeFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRequestTypes', $this->route('workspace'));
    }

    public function rules(): array
    {
        $requestType = $this->route('requestType');
        $type = RequestFieldType::tryFrom((string) $this->input('type'));

        return [
            'key' => [
                'required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/',
                Rule::unique((new RequestTypeField)->getTable(), 'key')->where('request_type_id', $requestType?->id),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(RequestFieldType::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_required' => ['sometimes', 'boolean'],
            'config' => [
                Rule::requiredIf(in_array($type, [RequestFieldType::Select, RequestFieldType::Multiselect], true)),
                'nullable', 'array', new RequestFieldConfig($type),
            ],
            'request_type_id' => ['prohibited'],
            'position' => ['prohibited'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RequestTypeField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderRequestTypeFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRequestTypes', $this->route('workspace'));
    }

    public function rules(): array
    {
        return [
            'field_ids' => ['required', 'array'],
            'field_ids.*' => [
                'required', 'integer', 'distinct:strict',
                Rule::exists((new RequestTypeField)->getTable(), 'id')
                    ->where('request_type_id', $this->route('requestType')?->id),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! is_array($this->input('field_ids'))) {
                return;
            }

            if (count($this->input('field_ids')) !== $this->route('requestType')->fields()->count()) {
                $validator->errors()->add('field_ids', 'The field_ids must contain the complete field set.');
            }
        }];
    }
}

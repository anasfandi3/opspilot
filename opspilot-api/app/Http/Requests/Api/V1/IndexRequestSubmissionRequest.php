<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RequestStatus;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequestSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [RequestSubmission::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(RequestStatus::class)],
            'request_type_id' => [
                'sometimes',
                'integer',
                Rule::exists((new RequestType)->getTable(), 'id')
                    ->where('workspace_id', $this->route('workspace')?->id),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

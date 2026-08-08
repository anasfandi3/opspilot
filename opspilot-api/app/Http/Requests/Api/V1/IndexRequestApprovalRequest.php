<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RequestApprovalStatus;
use App\Models\RequestApproval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequestApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [RequestApproval::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(RequestApprovalStatus::class)->except(RequestApprovalStatus::Skipped)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

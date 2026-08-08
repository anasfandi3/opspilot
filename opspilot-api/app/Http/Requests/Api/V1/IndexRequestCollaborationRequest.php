<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RequestSubmission;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequestCollaborationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('requestSubmission');

        return $submission instanceof RequestSubmission && $this->user()->can('view', $submission);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

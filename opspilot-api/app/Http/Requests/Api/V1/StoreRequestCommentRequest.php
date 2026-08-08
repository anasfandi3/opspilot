<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RequestSubmission;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequestCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('requestSubmission');

        return $submission instanceof RequestSubmission && $this->user()->can('collaborate', $submission);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }
}

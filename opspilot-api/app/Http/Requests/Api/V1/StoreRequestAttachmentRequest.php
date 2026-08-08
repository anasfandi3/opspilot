<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RequestSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreRequestAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('requestSubmission');

        return $submission instanceof RequestSubmission && $this->user()->can('collaborate', $submission);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['pdf', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx'])
                    ->max((int) config('filesystems.attachments.max_kb', 10240)),
            ],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $file = $this->file('file');
            if ($file && mb_strlen($file->getClientOriginalName()) > 255) {
                $validator->errors()->add('file', 'The original file name must not exceed 255 characters.');
            }
        }];
    }
}

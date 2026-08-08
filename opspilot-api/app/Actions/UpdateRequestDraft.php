<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Support\RequestPayloadValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRequestDraft
{
    public function __construct(private RequestPayloadValidator $validator) {}

    /** @param array<string, mixed> $data */
    public function handle(RequestSubmission $submission, array $data): RequestSubmission
    {
        return DB::transaction(function () use ($submission, $data): RequestSubmission {
            $requestType = RequestType::query()->lockForUpdate()->findOrFail($submission->request_type_id);
            $locked = RequestSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            if ($locked->status !== RequestStatus::Draft) {
                throw ValidationException::withMessages(['request' => 'Only draft requests may be updated.']);
            }

            if (array_key_exists('payload', $data)) {
                $requestType->setRelation('fields', $requestType->fields()->lockForUpdate()->get());
                $this->validator->validateDraft($requestType, $data['payload']);
                $locked->forceFill(['payload' => (object) $data['payload']])->save();
            }

            return $locked->refresh();
        }, attempts: 3);
    }
}

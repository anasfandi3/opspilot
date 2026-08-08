<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelRequest
{
    public function handle(RequestSubmission $submission): RequestSubmission
    {
        return DB::transaction(function () use ($submission): RequestSubmission {
            RequestType::query()->lockForUpdate()->findOrFail($submission->request_type_id);
            $locked = RequestSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            if (! in_array($locked->status, [RequestStatus::Draft, RequestStatus::Submitted], true)) {
                throw ValidationException::withMessages(['request' => 'This request cannot be cancelled.']);
            }

            $locked->forceFill([
                'status' => RequestStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            return $locked->refresh();
        }, attempts: 3);
    }
}

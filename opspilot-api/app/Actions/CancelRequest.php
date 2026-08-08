<?php

namespace App\Actions;

use App\Enums\RequestActivityType;
use App\Enums\RequestApprovalStatus;
use App\Enums\RequestStatus;
use App\Models\RequestApproval;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\User;
use App\Support\RequestActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelRequest
{
    public function __construct(private RequestActivityRecorder $activities) {}

    public function handle(RequestSubmission $submission, User $actor): RequestSubmission
    {
        return DB::transaction(function () use ($submission, $actor): RequestSubmission {
            RequestType::query()->lockForUpdate()->findOrFail($submission->request_type_id);
            $locked = RequestSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            if (! in_array($locked->status, [RequestStatus::Draft, RequestStatus::Submitted], true)) {
                throw ValidationException::withMessages(['request' => 'This request cannot be cancelled.']);
            }

            if ($locked->status === RequestStatus::Submitted) {
                $openApprovals = RequestApproval::query()
                    ->where('request_submission_id', $locked->id)
                    ->whereIn('status', [RequestApprovalStatus::Pending, RequestApprovalStatus::Waiting])
                    ->orderBy('position')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                foreach ($openApprovals as $approval) {
                    $approval->forceFill([
                        'status' => RequestApprovalStatus::Cancelled,
                        'pending_guard' => null,
                    ])->save();
                }
            }

            $locked->forceFill([
                'status' => RequestStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();
            $this->activities->record(
                $locked,
                RequestActivityType::RequestCancelled,
                actor: $actor,
            );

            return $locked->refresh();
        }, attempts: 3);
    }
}

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
use App\Support\RequestApprovalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveRequestApproval
{
    public function __construct(
        private RequestApprovalAccess $access,
        private RequestActivityRecorder $activities,
    ) {}

    public function handle(RequestApproval $approval, User $actor): RequestApproval
    {
        $requestTypeId = RequestSubmission::query()->findOrFail($approval->request_submission_id)->request_type_id;

        return DB::transaction(function () use ($approval, $actor, $requestTypeId): RequestApproval {
            RequestType::query()->lockForUpdate()->findOrFail($requestTypeId);
            $submission = RequestSubmission::query()->lockForUpdate()->findOrFail($approval->request_submission_id);
            $locked = RequestApproval::query()
                ->where('request_submission_id', $submission->id)
                ->lockForUpdate()
                ->findOrFail($approval->id);
            $this->ensureActionable($submission, $locked, $actor);

            $decidedAt = now();
            $locked->forceFill([
                'status' => RequestApprovalStatus::Approved,
                'pending_guard' => null,
                'decided_by' => $actor->id,
                'decided_at' => $decidedAt,
            ])->save();
            $step = $locked->workflowStep()->firstOrFail();
            $this->activities->record(
                $submission,
                RequestActivityType::ApprovalApproved,
                actor: $actor,
                approval: $locked,
                metadata: ['workflow_step_id' => $step->id, 'workflow_step_name' => $step->name],
            );

            $waiting = RequestApproval::query()
                ->where('request_submission_id', $submission->id)
                ->where('status', RequestApprovalStatus::Waiting)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $next = $waiting->first();

            if ($next) {
                $next->forceFill([
                    'status' => RequestApprovalStatus::Pending,
                    'pending_guard' => 1,
                    'activated_at' => $decidedAt,
                ])->save();
                $nextStep = $next->workflowStep()->firstOrFail();
                $this->activities->record(
                    $submission,
                    RequestActivityType::ApprovalActivated,
                    approval: $next,
                    metadata: ['workflow_step_id' => $nextStep->id, 'workflow_step_name' => $nextStep->name],
                );
            } else {
                $submission->forceFill([
                    'status' => RequestStatus::Approved,
                    'resolved_at' => $decidedAt,
                ])->save();
                $this->activities->record(
                    $submission,
                    RequestActivityType::RequestApproved,
                    actor: $actor,
                );
            }

            return $locked->refresh();
        }, attempts: 3);
    }

    private function ensureActionable(RequestSubmission $submission, RequestApproval $approval, User $actor): void
    {
        if ($submission->status !== RequestStatus::Submitted
            || $approval->status !== RequestApprovalStatus::Pending
            || $approval->workspace_id !== $submission->workspace_id
            || ! $this->access->canAct($actor, $approval)) {
            throw ValidationException::withMessages(['approval' => 'This approval is no longer actionable.']);
        }
    }
}

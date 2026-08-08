<?php

namespace App\Actions;

use App\Enums\RequestActivityType;
use App\Enums\RequestStatus;
use App\Enums\WorkflowStatus;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RequestActivityRecorder;
use App\Support\RequestPayloadValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRequestSubmission
{
    public function __construct(
        private RequestPayloadValidator $validator,
        private RequestActivityRecorder $activities,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(Workspace $workspace, RequestType $requestType, User $creator, array $payload): RequestSubmission
    {
        return DB::transaction(function () use ($workspace, $requestType, $creator, $payload): RequestSubmission {
            $lockedRequestType = RequestType::query()->lockForUpdate()->findOrFail($requestType->id);
            if ($lockedRequestType->workspace_id !== $workspace->id || ! $lockedRequestType->is_active) {
                throw ValidationException::withMessages(['request_type' => 'The request type is not available.']);
            }
            if (! $lockedRequestType->workflows()->where('status', WorkflowStatus::Active)->exists()) {
                throw ValidationException::withMessages(['request_type' => 'The request type does not have an active workflow.']);
            }

            $lockedRequestType->setRelation('fields', $lockedRequestType->fields()->lockForUpdate()->get());
            $this->validator->validateDraft($lockedRequestType, $payload);

            $submission = new RequestSubmission;
            $submission->forceFill([
                'status' => RequestStatus::Draft,
                'payload' => (object) $payload,
            ]);
            $submission->workspace()->associate($workspace);
            $submission->requestType()->associate($lockedRequestType);
            $submission->creator()->associate($creator);
            $submission->save();
            $this->activities->record(
                $submission,
                RequestActivityType::RequestCreated,
                actor: $creator,
                metadata: ['request_type_id' => $lockedRequestType->id],
            );

            return $submission;
        }, attempts: 3);
    }
}

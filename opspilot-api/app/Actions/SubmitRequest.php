<?php

namespace App\Actions;

use App\Enums\RequestActivityType;
use App\Enums\RequestStatus;
use App\Enums\WorkflowStatus;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Support\RequestActivityRecorder;
use App\Support\RequestNotificationDispatcher;
use App\Support\RequestPayloadValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitRequest
{
    public function __construct(
        private RequestPayloadValidator $validator,
        private InitializeRequestApprovals $initializeApprovals,
        private RequestActivityRecorder $activities,
        private RequestNotificationDispatcher $notifications,
    ) {}

    public function handle(RequestSubmission $submission): RequestSubmission
    {
        return DB::transaction(function () use ($submission): RequestSubmission {
            $requestType = RequestType::query()->lockForUpdate()->findOrFail($submission->request_type_id);
            $locked = RequestSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            if ($locked->status !== RequestStatus::Draft) {
                throw ValidationException::withMessages(['request' => 'Only draft requests may be submitted.']);
            }
            if (! $requestType->is_active) {
                throw ValidationException::withMessages(['request_type' => 'The request type is not active.']);
            }

            $workflow = $requestType->workflows()->where('status', WorkflowStatus::Active)->lockForUpdate()->first();
            if (! $workflow) {
                throw ValidationException::withMessages(['workflow' => 'The request type does not have an active workflow.']);
            }

            $requestType->setRelation('fields', $requestType->fields()->lockForUpdate()->get());
            $this->validator->validateSubmission($requestType, $locked->payload);
            $workflow->setRelation(
                'steps',
                $workflow->steps()->with('conditions.requestTypeField')->lockForUpdate()->get(),
            );
            $workflow->setRelation('workspace', $requestType->workspace()->firstOrFail());

            $locked->forceFill([
                'workflow_id' => $workflow->id,
                'definition_snapshot' => $this->snapshot($requestType),
                'submitted_at' => now(),
            ]);

            $activatedApproval = $this->initializeApprovals->handle($locked, $workflow);
            $locked->forceFill([
                'status' => $activatedApproval ? RequestStatus::Submitted : RequestStatus::Approved,
                'resolved_at' => $activatedApproval ? null : now(),
            ])->save();
            $creator = $locked->creator()->firstOrFail();
            $this->activities->record(
                $locked,
                RequestActivityType::RequestSubmitted,
                actor: $creator,
                metadata: ['workflow_id' => $workflow->id, 'workflow_version' => $workflow->version],
            );
            if ($activatedApproval) {
                $step = $activatedApproval->workflowStep()->firstOrFail();
                $this->activities->record(
                    $locked,
                    RequestActivityType::ApprovalActivated,
                    approval: $activatedApproval,
                    metadata: ['workflow_step_id' => $step->id, 'workflow_step_name' => $step->name],
                );
                $this->notifications->approvalActivated($activatedApproval);
            } else {
                $this->activities->record($locked, RequestActivityType::RequestApproved);
                $this->notifications->requestApproved($locked);
            }

            return $locked->refresh();
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    private function snapshot(RequestType $requestType): array
    {
        return [
            'request_type' => [
                'id' => $requestType->id,
                'name' => $requestType->name,
                'slug' => $requestType->slug,
            ],
            'fields' => $requestType->fields->map(fn (RequestTypeField $field): array => [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type->value,
                'description' => $field->description,
                'is_required' => $field->is_required,
                'position' => $field->position,
                'config' => $field->config,
            ])->all(),
        ];
    }
}

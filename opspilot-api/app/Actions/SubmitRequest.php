<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Enums\WorkflowStatus;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Support\RequestPayloadValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitRequest
{
    public function __construct(private RequestPayloadValidator $validator) {}

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

            $locked->forceFill([
                'workflow_id' => $workflow->id,
                'status' => RequestStatus::Submitted,
                'definition_snapshot' => $this->snapshot($requestType),
                'submitted_at' => now(),
            ])->save();

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

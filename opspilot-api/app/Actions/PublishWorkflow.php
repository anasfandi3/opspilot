<?php

namespace App\Actions;

use App\Enums\WorkflowStatus;
use App\Models\RequestType;
use App\Models\Workflow;
use App\Support\WorkflowDefinitionValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishWorkflow
{
    public function __construct(private WorkflowDefinitionValidator $validator) {}

    public function handle(Workflow $workflow): Workflow
    {
        return DB::transaction(function () use ($workflow): Workflow {
            RequestType::query()->lockForUpdate()->findOrFail($workflow->request_type_id);
            $locked = Workflow::query()->lockForUpdate()->findOrFail($workflow->id);
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['workflow' => 'Only draft workflows may be published.']);
            }

            $locked->load(['workspace', 'requestType', 'steps.conditions.requestTypeField']);
            $this->validator->validateWorkflow($locked);
            Workflow::query()->where('request_type_id', $locked->request_type_id)
                ->where('status', WorkflowStatus::Active)
                ->whereKeyNot($locked->id)
                ->update(['status' => WorkflowStatus::Archived, 'active_guard' => null]);
            $locked->forceFill([
                'status' => WorkflowStatus::Active,
                'draft_guard' => null,
                'active_guard' => 1,
                'published_at' => now(),
            ])->save();

            return $locked->refresh();
        }, attempts: 3);
    }
}

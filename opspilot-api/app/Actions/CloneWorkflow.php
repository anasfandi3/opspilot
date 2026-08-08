<?php

namespace App\Actions;

use App\Enums\WorkflowStatus;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepCondition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloneWorkflow
{
    public function handle(Workflow $source, User $creator): Workflow
    {
        return DB::transaction(function () use ($source, $creator): Workflow {
            $requestType = RequestType::query()->lockForUpdate()->findOrFail($source->request_type_id);
            $lockedSource = Workflow::query()->lockForUpdate()->findOrFail($source->id);
            if (! in_array($lockedSource->status, [WorkflowStatus::Active, WorkflowStatus::Archived], true)) {
                throw ValidationException::withMessages(['workflow' => 'Only published workflows may be cloned.']);
            }
            if ($requestType->workflows()->where('status', WorkflowStatus::Draft)->exists()) {
                throw ValidationException::withMessages(['workflow' => 'A draft workflow already exists.']);
            }

            $nextVersion = ((int) $requestType->workflows()->max('version')) + 1;
            $clone = $lockedSource->replicate(['status', 'draft_guard', 'active_guard', 'created_by', 'published_at']);
            $clone->forceFill([
                'version' => $nextVersion,
                'status' => WorkflowStatus::Draft,
                'draft_guard' => 1,
                'active_guard' => null,
                'created_by' => $creator->id,
                'published_at' => null,
            ])->save();

            $lockedSource->load('steps.conditions');
            foreach ($lockedSource->steps as $sourceStep) {
                /** @var WorkflowStep $step */
                $step = $sourceStep->replicate();
                $step->workflow()->associate($clone);
                $step->save();
                foreach ($sourceStep->conditions as $sourceCondition) {
                    /** @var WorkflowStepCondition $condition */
                    $condition = $sourceCondition->replicate();
                    $condition->step()->associate($step);
                    $condition->save();
                }
            }

            return $clone->load(['creator:id,name', 'steps.approverUser:id,name,email', 'steps.conditions.requestTypeField']);
        }, attempts: 3);
    }
}

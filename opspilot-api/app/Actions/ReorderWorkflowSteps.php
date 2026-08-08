<?php

namespace App\Actions;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderWorkflowSteps
{
    /** @param list<int> $stepIds */
    public function handle(Workflow $workflow, array $stepIds): void
    {
        DB::transaction(function () use ($workflow, $stepIds): void {
            $locked = Workflow::query()->lockForUpdate()->findOrFail($workflow->id);
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['workflow' => 'Only draft workflows may be modified.']);
            }
            $steps = $locked->steps()->reorder()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $current = $steps->keys()->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all();
            $submitted = collect($stepIds)->sort()->values()->all();
            if ($current !== $submitted || count($stepIds) !== count(array_unique($stepIds))) {
                throw ValidationException::withMessages(['step_ids' => 'The step_ids must contain the complete unique step set.']);
            }
            foreach ($stepIds as $index => $stepId) {
                /** @var WorkflowStep $step */
                $step = $steps->get($stepId);
                $step->forceFill(['position' => $index + 1])->save();
            }
        }, attempts: 3);
    }
}

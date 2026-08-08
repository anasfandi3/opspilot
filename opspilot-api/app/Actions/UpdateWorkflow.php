<?php

namespace App\Actions;

use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateWorkflow
{
    /** @param array<string, mixed> $data */
    public function handle(Workflow $workflow, array $data): Workflow
    {
        return DB::transaction(function () use ($workflow, $data): Workflow {
            $locked = Workflow::query()->lockForUpdate()->findOrFail($workflow->id);
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['workflow' => 'Only draft workflows may be modified.']);
            }
            $locked->update($data);

            return $locked->refresh();
        });
    }
}

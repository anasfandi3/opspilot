<?php

namespace App\Actions;

use App\Models\RequestType;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteWorkflow
{
    public function handle(Workflow $workflow): void
    {
        DB::transaction(function () use ($workflow): void {
            RequestType::query()->lockForUpdate()->findOrFail($workflow->request_type_id);
            $locked = Workflow::query()->lockForUpdate()->findOrFail($workflow->id);
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['workflow' => 'Only draft workflows may be deleted.']);
            }
            $locked->delete();
        });
    }
}

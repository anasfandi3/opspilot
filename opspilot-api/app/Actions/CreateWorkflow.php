<?php

namespace App\Actions;

use App\Enums\WorkflowStatus;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateWorkflow
{
    /** @param array{name: string, description?: ?string} $data */
    public function handle(Workspace $workspace, RequestType $requestType, User $creator, array $data): Workflow
    {
        return DB::transaction(function () use ($workspace, $requestType, $creator, $data): Workflow {
            $lockedRequestType = RequestType::query()->lockForUpdate()->findOrFail($requestType->id);
            if ($lockedRequestType->workspace_id !== $workspace->id || $lockedRequestType->workflows()->exists()) {
                throw ValidationException::withMessages(['workflow' => 'The first workflow has already been created.']);
            }

            $workflow = new Workflow($data);
            $workflow->forceFill([
                'version' => 1,
                'status' => WorkflowStatus::Draft,
                'draft_guard' => 1,
                'active_guard' => null,
            ]);
            $workflow->workspace()->associate($workspace);
            $workflow->requestType()->associate($lockedRequestType);
            $workflow->creator()->associate($creator);
            $workflow->save();

            return $workflow;
        }, attempts: 3);
    }
}

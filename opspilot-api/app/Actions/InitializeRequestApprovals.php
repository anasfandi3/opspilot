<?php

namespace App\Actions;

use App\Enums\RequestApprovalStatus;
use App\Models\RequestApproval;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Support\ApprovalAssigneeResolver;
use App\Support\WorkflowConditionEvaluator;
use Illuminate\Validation\ValidationException;

class InitializeRequestApprovals
{
    public function __construct(
        private WorkflowConditionEvaluator $evaluator,
        private ApprovalAssigneeResolver $assignees,
    ) {}

    public function handle(RequestSubmission $submission, Workflow $workflow): bool
    {
        $workflow->loadMissing(['workspace', 'steps.conditions.requestTypeField']);
        if ($workflow->steps->isEmpty()) {
            throw ValidationException::withMessages(['workflow' => 'The active workflow has no steps.']);
        }

        $firstApplicable = null;
        foreach ($workflow->steps as $step) {
            $applies = $this->evaluator->applies($step, $submission->payload);
            $approval = $this->createApproval($submission, $step, $applies);
            if (! $applies) {
                continue;
            }

            $users = $this->assignees->resolve($step, $workflow->workspace);
            $approval->assignees()->createMany(
                $users->map(fn (User $user): array => ['user_id' => $user->id])->all(),
            );
            $firstApplicable ??= $approval;
        }

        if ($firstApplicable === null) {
            return false;
        }

        $firstApplicable->forceFill([
            'status' => RequestApprovalStatus::Pending,
            'pending_guard' => 1,
            'activated_at' => now(),
        ])->save();

        return true;
    }

    private function createApproval(RequestSubmission $submission, WorkflowStep $step, bool $applies): RequestApproval
    {
        $approval = new RequestApproval([
            'position' => $step->position,
            'status' => $applies ? RequestApprovalStatus::Waiting : RequestApprovalStatus::Skipped,
        ]);
        $approval->forceFill(['pending_guard' => null]);
        $approval->workspace()->associate($submission->workspace_id);
        $approval->requestSubmission()->associate($submission);
        $approval->workflowStep()->associate($step);
        $approval->save();

        return $approval;
    }
}

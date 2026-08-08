<?php

namespace App\Support;

use App\Enums\RequestActivityType;
use App\Models\RequestActivity;
use App\Models\RequestApproval;
use App\Models\RequestAttachment;
use App\Models\RequestComment;
use App\Models\RequestSubmission;
use App\Models\User;

class RequestActivityRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(
        RequestSubmission $submission,
        RequestActivityType $type,
        ?User $actor = null,
        ?RequestComment $comment = null,
        ?RequestAttachment $attachment = null,
        ?RequestApproval $approval = null,
        array $metadata = [],
    ): RequestActivity {
        $activity = new RequestActivity([
            'type' => $type,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
        $activity->workspace()->associate($submission->workspace_id);
        $activity->requestSubmission()->associate($submission);
        $activity->actor()->associate($actor);
        $activity->comment()->associate($comment);
        $activity->attachment()->associate($attachment);
        $activity->approval()->associate($approval);
        $activity->save();

        return $activity;
    }
}

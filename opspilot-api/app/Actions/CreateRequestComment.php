<?php

namespace App\Actions;

use App\Enums\RequestActivityType;
use App\Models\RequestComment;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Support\RequestActivityRecorder;
use Illuminate\Support\Facades\DB;

class CreateRequestComment
{
    public function __construct(private RequestActivityRecorder $activities) {}

    public function handle(RequestSubmission $submission, User $author, string $body): RequestComment
    {
        return DB::transaction(function () use ($submission, $author, $body): RequestComment {
            $comment = new RequestComment(['body' => trim($body)]);
            $comment->workspace()->associate($submission->workspace_id);
            $comment->requestSubmission()->associate($submission);
            $comment->author()->associate($author);
            $comment->save();

            $this->activities->record(
                $submission,
                RequestActivityType::CommentAdded,
                actor: $author,
                comment: $comment,
            );

            return $comment;
        });
    }
}

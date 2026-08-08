<?php

namespace App\Support;

use App\Models\RequestApproval;
use App\Models\RequestAttachment;
use App\Models\RequestComment;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Notifications\ApprovalAssignedNotification;
use App\Notifications\RequestApprovedNotification;
use App\Notifications\RequestAttachmentUploadedNotification;
use App\Notifications\RequestCancelledNotification;
use App\Notifications\RequestCommentAddedNotification;
use App\Notifications\RequestRejectedNotification;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class RequestNotificationDispatcher
{
    public function __construct(private RequestNotificationRecipients $recipients) {}

    public function approvalActivated(RequestApproval $approval): void
    {
        $approvalId = $approval->id;

        $this->afterCommit(function () use ($approvalId): void {
            $approval = RequestApproval::query()->findOrFail($approvalId);
            $approval->loadMissing(['workspace:id,name', 'requestSubmission.requestType:id,name', 'workflowStep:id,name']);
            Notification::send(
                $this->recipients->approvalAssignees($approval),
                new ApprovalAssignedNotification($this->approvalPayload($approval)),
            );
        });
    }

    public function requestApproved(RequestSubmission $submission): void
    {
        $submissionId = $submission->id;

        $this->afterCommit(function () use ($submissionId): void {
            $submission = RequestSubmission::query()->findOrFail($submissionId);
            $creator = $this->recipients->requestCreator($submission);
            if ($creator) {
                Notification::send($creator, new RequestApprovedNotification([
                    ...$this->requestPayload($submission),
                    'event' => 'request_approved',
                    'message' => $this->requestLabel($submission).' was approved.',
                ]));
            }
        });
    }

    public function requestRejected(RequestSubmission $submission, RequestApproval $approval, User $actor): void
    {
        $submissionId = $submission->id;
        $approvalId = $approval->id;
        $actorId = $actor->id;

        $this->afterCommit(function () use ($submissionId, $approvalId, $actorId): void {
            $submission = RequestSubmission::query()->findOrFail($submissionId);
            $approval = RequestApproval::query()->findOrFail($approvalId);
            $actor = User::query()->findOrFail($actorId);
            $creator = $this->recipients->requestCreator($submission);
            if ($creator) {
                Notification::send($creator, new RequestRejectedNotification([
                    ...$this->requestPayload($submission),
                    'event' => 'request_rejected',
                    'approval' => $this->approvalSummary($approval),
                    'actor' => $this->actorSummary($actor),
                    'message' => $this->requestLabel($submission).' was rejected.',
                ]));
            }
        });
    }

    public function requestCancelled(RequestSubmission $submission, RequestApproval $pending, User $actor): void
    {
        $submissionId = $submission->id;
        $pendingId = $pending->id;
        $actorId = $actor->id;

        $this->afterCommit(function () use ($submissionId, $pendingId, $actorId): void {
            $submission = RequestSubmission::query()->findOrFail($submissionId);
            $pending = RequestApproval::query()->findOrFail($pendingId);
            $actor = User::query()->findOrFail($actorId);
            Notification::send(
                $this->recipients->approvalAssignees($pending, $actor),
                new RequestCancelledNotification([
                    ...$this->requestPayload($submission),
                    'event' => 'request_cancelled',
                    'approval' => $this->approvalSummary($pending),
                    'actor' => $this->actorSummary($actor),
                    'message' => $this->requestLabel($submission).' was cancelled.',
                ]),
            );
        });
    }

    public function commentAdded(RequestComment $comment): void
    {
        $commentId = $comment->id;

        $this->afterCommit(function () use ($commentId): void {
            $comment = RequestComment::query()->findOrFail($commentId);
            $comment->loadMissing(['requestSubmission', 'author:id,name']);
            $submission = $comment->requestSubmission;
            Notification::send(
                $this->recipients->collaborationParticipants($submission, $comment->author),
                new RequestCommentAddedNotification([
                    ...$this->requestPayload($submission),
                    'event' => 'comment_added',
                    'actor' => $this->actorSummary($comment->author),
                    'comment' => ['id' => $comment->id],
                    'message' => $comment->author->name.' added a comment to '.$this->requestLabel($submission).'.',
                ]),
            );
        });
    }

    public function attachmentUploaded(RequestAttachment $attachment): void
    {
        $attachmentId = $attachment->id;

        $this->afterCommit(function () use ($attachmentId): void {
            $attachment = RequestAttachment::query()->findOrFail($attachmentId);
            $attachment->loadMissing(['requestSubmission', 'uploader:id,name']);
            $submission = $attachment->requestSubmission;
            Notification::send(
                $this->recipients->collaborationParticipants($submission, $attachment->uploader),
                new RequestAttachmentUploadedNotification([
                    ...$this->requestPayload($submission),
                    'event' => 'attachment_uploaded',
                    'actor' => $this->actorSummary($attachment->uploader),
                    'attachment' => [
                        'id' => $attachment->id,
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'size_bytes' => $attachment->size_bytes,
                    ],
                    'message' => $attachment->uploader->name.' uploaded an attachment to '.$this->requestLabel($submission).'.',
                ]),
            );
        });
    }

    private function afterCommit(Closure $operation): void
    {
        DB::afterCommit(function () use ($operation): void {
            try {
                $operation();
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /** @return array<string, mixed> */
    private function requestPayload(RequestSubmission $submission): array
    {
        $submission->loadMissing(['workspace:id,name', 'requestType:id,name']);

        return [
            'workspace' => ['id' => $submission->workspace->id, 'name' => $submission->workspace->name],
            'request' => [
                'id' => $submission->id,
                'request_type' => [
                    'id' => $submission->requestType->id,
                    'name' => $submission->requestType->name,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function approvalPayload(RequestApproval $approval): array
    {
        return [
            ...$this->requestPayload($approval->requestSubmission),
            'event' => 'approval_assigned',
            'approval' => $this->approvalSummary($approval),
            'message' => 'A '.$approval->requestSubmission->requestType->name.' is waiting for your approval.',
        ];
    }

    /** @return array{id: int, position: int, workflow_step_name: string} */
    private function approvalSummary(RequestApproval $approval): array
    {
        $approval->loadMissing('workflowStep:id,name');

        return [
            'id' => $approval->id,
            'position' => $approval->position,
            'workflow_step_name' => $approval->workflowStep->name,
        ];
    }

    /** @return array{id: int, name: string} */
    private function actorSummary(User $actor): array
    {
        return ['id' => $actor->id, 'name' => $actor->name];
    }

    private function requestLabel(RequestSubmission $submission): string
    {
        $submission->loadMissing('requestType:id,name');

        return $submission->requestType->name.' #'.$submission->id;
    }
}

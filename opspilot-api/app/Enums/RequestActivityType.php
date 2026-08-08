<?php

namespace App\Enums;

enum RequestActivityType: string
{
    case RequestCreated = 'request_created';
    case RequestSubmitted = 'request_submitted';
    case RequestCancelled = 'request_cancelled';
    case RequestApproved = 'request_approved';
    case RequestRejected = 'request_rejected';
    case ApprovalActivated = 'approval_activated';
    case ApprovalApproved = 'approval_approved';
    case ApprovalRejected = 'approval_rejected';
    case CommentAdded = 'comment_added';
    case AttachmentUploaded = 'attachment_uploaded';
}

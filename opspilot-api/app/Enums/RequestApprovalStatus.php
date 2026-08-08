<?php

namespace App\Enums;

enum RequestApprovalStatus: string
{
    case Waiting = 'waiting';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}

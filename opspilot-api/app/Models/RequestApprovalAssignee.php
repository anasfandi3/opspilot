<?php

namespace App\Models;

use Database\Factories\RequestApprovalAssigneeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id'])]
class RequestApprovalAssignee extends Model
{
    /** @use HasFactory<RequestApprovalAssigneeFactory> */
    use HasFactory;

    public function approval(): BelongsTo
    {
        return $this->belongsTo(RequestApproval::class, 'request_approval_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

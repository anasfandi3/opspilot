<?php

namespace App\Models;

use App\Enums\RequestActivityType;
use Database\Factories\RequestActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'metadata'])]
class RequestActivity extends Model
{
    /** @use HasFactory<RequestActivityFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function requestSubmission(): BelongsTo
    {
        return $this->belongsTo(RequestSubmission::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(RequestComment::class, 'request_comment_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(RequestAttachment::class, 'request_attachment_id');
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(RequestApproval::class, 'request_approval_id');
    }

    protected function casts(): array
    {
        return [
            'type' => RequestActivityType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

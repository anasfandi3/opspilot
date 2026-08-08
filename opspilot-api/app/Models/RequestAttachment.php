<?php

namespace App\Models;

use Database\Factories\RequestAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size_bytes'])]
class RequestAttachment extends Model
{
    /** @use HasFactory<RequestAttachmentFactory> */
    use HasFactory;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function requestSubmission(): BelongsTo
    {
        return $this->belongsTo(RequestSubmission::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }
}

<?php

namespace App\Models;

use Database\Factories\RequestCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['body'])]
class RequestComment extends Model
{
    /** @use HasFactory<RequestCommentFactory> */
    use HasFactory;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function requestSubmission(): BelongsTo
    {
        return $this->belongsTo(RequestSubmission::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Database\Factories\RequestSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payload'])]
class RequestSubmission extends Model
{
    /** @use HasFactory<RequestSubmissionFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'payload' => '{}',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'payload' => 'array',
            'definition_snapshot' => 'array',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}

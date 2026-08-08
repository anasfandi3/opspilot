<?php

namespace App\Models;

use App\Enums\RequestApprovalStatus;
use Database\Factories\RequestApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['position', 'status', 'activated_at', 'decided_at'])]
class RequestApproval extends Model
{
    /** @use HasFactory<RequestApprovalFactory> */
    use HasFactory;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function requestSubmission(): BelongsTo
    {
        return $this->belongsTo(RequestSubmission::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(RequestApprovalAssignee::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(RequestActivity::class);
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => RequestApprovalStatus::class,
            'activated_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}

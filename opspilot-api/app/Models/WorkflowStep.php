<?php

namespace App\Models;

use App\Enums\WorkflowApproverType;
use App\Enums\WorkflowConditionLogic;
use App\Enums\WorkspaceRole;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'approver_type', 'approver_role', 'approver_user_id', 'condition_logic'])]
class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory;

    protected $attributes = ['condition_logic' => 'all'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(WorkflowStepCondition::class)->orderBy('position')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'approver_type' => WorkflowApproverType::class,
            'approver_role' => WorkspaceRole::class,
            'condition_logic' => WorkflowConditionLogic::class,
        ];
    }
}

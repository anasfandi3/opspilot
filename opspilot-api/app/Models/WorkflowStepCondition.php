<?php

namespace App\Models;

use App\Enums\WorkflowConditionOperator;
use Database\Factories\WorkflowStepConditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_type_field_id', 'operator', 'value', 'position'])]
class WorkflowStepCondition extends Model
{
    /** @use HasFactory<WorkflowStepConditionFactory> */
    use HasFactory;

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function requestTypeField(): BelongsTo
    {
        return $this->belongsTo(RequestTypeField::class);
    }

    protected function casts(): array
    {
        return [
            'operator' => WorkflowConditionOperator::class,
            'value' => 'json',
            'position' => 'integer',
        ];
    }
}

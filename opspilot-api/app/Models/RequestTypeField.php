<?php

namespace App\Models;

use App\Enums\RequestFieldType;
use Database\Factories\RequestTypeFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'description', 'is_required', 'config'])]
class RequestTypeField extends Model
{
    /** @use HasFactory<RequestTypeFieldFactory> */
    use HasFactory;

    protected $attributes = ['is_required' => false];

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function workflowConditions(): HasMany
    {
        return $this->hasMany(WorkflowStepCondition::class);
    }

    protected function casts(): array
    {
        return [
            'type' => RequestFieldType::class,
            'is_required' => 'boolean',
            'position' => 'integer',
            'config' => 'array',
        ];
    }
}

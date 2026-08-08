<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use Database\Factories\RequestTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'description', 'is_active'])]
class RequestType extends Model
{
    /** @use HasFactory<RequestTypeFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(RequestTypeField::class)->orderBy('position')->orderBy('id');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class)->latest('version');
    }

    public function activeWorkflow(): HasOne
    {
        return $this->hasOne(Workflow::class)->where('status', WorkflowStatus::Active);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

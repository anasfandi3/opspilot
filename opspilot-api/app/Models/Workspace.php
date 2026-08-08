<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->using(WorkspaceMembership::class)
            ->withPivot(['id', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function membershipFor(User $user): ?WorkspaceMembership
    {
        return $this->memberships()->whereBelongsTo($user)->first();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function requestTypes(): HasMany
    {
        return $this->hasMany(RequestType::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function requestSubmissions(): HasMany
    {
        return $this->hasMany(RequestSubmission::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class);
    }

    public function requestComments(): HasMany
    {
        return $this->hasMany(RequestComment::class);
    }

    public function requestAttachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class);
    }

    public function requestActivities(): HasMany
    {
        return $this->hasMany(RequestActivity::class);
    }
}

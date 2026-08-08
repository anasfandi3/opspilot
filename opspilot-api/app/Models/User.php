<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->using(WorkspaceMembership::class)
            ->withPivot(['id', 'joined_at'])
            ->withTimestamps();
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function createdRequestSubmissions(): HasMany
    {
        return $this->hasMany(RequestSubmission::class, 'created_by');
    }

    public function approvalAssignments(): HasMany
    {
        return $this->hasMany(RequestApprovalAssignee::class);
    }

    public function approvalDecisions(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'decided_by');
    }

    public function requestComments(): HasMany
    {
        return $this->hasMany(RequestComment::class, 'author_id');
    }

    public function uploadedRequestAttachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'uploaded_by');
    }

    public function requestActivities(): HasMany
    {
        return $this->hasMany(RequestActivity::class, 'actor_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

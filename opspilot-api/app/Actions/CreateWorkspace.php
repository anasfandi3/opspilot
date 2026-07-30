<?php

namespace App\Actions;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspace
{
    private const int MAX_SLUG_INSERT_ATTEMPTS = 5;

    public function handle(User $user, string $name): Workspace
    {
        return DB::transaction(function () use ($user, $name): Workspace {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $workspace = $this->createWorkspaceRecord($lockedUser, $name);

            WorkspaceMembership::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $lockedUser->id,
                'role' => WorkspaceRole::Owner,
                'joined_at' => now(),
            ]);

            $lockedUser->forceFill(['current_workspace_id' => $workspace->id])->save();

            return $workspace;
        });
    }

    private function createWorkspaceRecord(User $owner, string $name): Workspace
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_SLUG_INSERT_ATTEMPTS; $attempt++) {
            $workspace = new Workspace([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
            ]);
            $workspace->owner()->associate($owner);

            try {
                $workspace->save();

                return $workspace;
            } catch (UniqueConstraintViolationException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException;
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'workspace';
        $slug = $baseSlug;
        $suffix = 2;

        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}

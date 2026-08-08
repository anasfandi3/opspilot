<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkspaceRoleLookup
{
    /** @param Collection<int, Workspace> $workspaces @return Collection<int, string> */
    public function forUser(User $user, Collection $workspaces): Collection
    {
        return $this->query($user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->whereIn('model_has_roles.workspace_id', $workspaces->modelKeys())
            ->pluck('roles.name', 'model_has_roles.workspace_id');
    }

    /** @param Collection<int, User> $users @return Collection<int, string> */
    public function forWorkspace(Workspace $workspace, Collection $users): Collection
    {
        return $this->query((new User)->getMorphClass())
            ->where('model_has_roles.workspace_id', $workspace->id)
            ->whereIn('model_has_roles.model_id', $users->modelKeys())
            ->pluck('roles.name', 'model_has_roles.model_id');
    }

    private function query(string $modelType): Builder
    {
        return DB::table('model_has_roles')
            ->join('roles', function ($join): void {
                $join->on('roles.id', '=', 'model_has_roles.role_id')
                    ->on('roles.workspace_id', '=', 'model_has_roles.workspace_id');
            })
            ->where('model_has_roles.model_type', $modelType);
    }
}

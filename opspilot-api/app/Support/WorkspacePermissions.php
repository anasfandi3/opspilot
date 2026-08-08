<?php

namespace App\Support;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Closure;

class WorkspacePermissions
{
    public function allows(User $user, Workspace $workspace, WorkspacePermission $permission): bool
    {
        return $this->within($workspace, function () use ($user, $permission): bool {
            $user->unsetRelation('roles')->unsetRelation('permissions');

            return $user->hasPermissionTo($permission->value);
        });
    }

    public function role(User $user, Workspace $workspace): ?WorkspaceRole
    {
        return $this->within($workspace, function () use ($user): ?WorkspaceRole {
            $user->unsetRelation('roles');
            $name = $user->getRoleNames()->first();

            return $name ? WorkspaceRole::tryFrom($name) : null;
        });
    }

    public function assign(User $user, Workspace $workspace, WorkspaceRole $role): void
    {
        $this->within($workspace, function () use ($user, $role): void {
            $user->unsetRelation('roles');
            $user->syncRoles([$role->value]);
        });
    }

    public function remove(User $user, Workspace $workspace): void
    {
        $this->within($workspace, function () use ($user): void {
            $user->unsetRelation('roles');
            $user->syncRoles([]);
        });
    }

    /** @template T @param Closure(): T $callback @return T */
    public function within(Workspace $workspace, Closure $callback): mixed
    {
        $previous = getPermissionsTeamId();
        setPermissionsTeamId($workspace->id);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previous);
        }
    }
}

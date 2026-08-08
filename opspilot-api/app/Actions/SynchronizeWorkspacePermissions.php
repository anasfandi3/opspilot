<?php

namespace App\Actions;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Support\WorkspacePermissions;
use App\Support\WorkspaceRoleMap;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SynchronizeWorkspacePermissions
{
    public function __construct(private WorkspacePermissions $permissions) {}

    public function handle(Workspace $workspace): void
    {
        foreach (WorkspacePermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        $this->permissions->within($workspace, function (): void {
            foreach (WorkspaceRole::cases() as $roleName) {
                $role = Role::findOrCreate($roleName->value, 'web');
                $role->syncPermissions(array_map(
                    static fn (WorkspacePermission $permission): string => $permission->value,
                    WorkspaceRoleMap::permissions($roleName),
                ));
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

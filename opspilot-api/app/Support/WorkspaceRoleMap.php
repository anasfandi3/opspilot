<?php

namespace App\Support;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;

class WorkspaceRoleMap
{
    /** @return list<WorkspacePermission> */
    public static function permissions(WorkspaceRole $role): array
    {
        return match ($role) {
            WorkspaceRole::Owner, WorkspaceRole::Admin => WorkspacePermission::cases(),
            WorkspaceRole::Approver => self::only([
                'workspace.view', 'members.view', 'approvals.view_assigned', 'approvals.act',
            ]),
            WorkspaceRole::Requester => self::only([
                'workspace.view', 'members.view', 'requests.create', 'requests.view_own',
                'requests.update_own', 'requests.submit', 'requests.cancel_own',
            ]),
            WorkspaceRole::Auditor => self::only([
                'workspace.view', 'members.view', 'requests.view_all', 'reports.view', 'audit_logs.view',
            ]),
        };
    }

    /** @param list<string> $names @return list<WorkspacePermission> */
    private static function only(array $names): array
    {
        return array_map(WorkspacePermission::from(...), $names);
    }
}

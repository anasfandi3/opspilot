<?php

namespace App\Enums;

enum WorkspacePermission: string
{
    case WorkspaceView = 'workspace.view';
    case WorkspaceUpdate = 'workspace.update';
    case MembersView = 'members.view';
    case MembersManage = 'members.manage';
    case MembersAssignRoles = 'members.assign_roles';
    case InvitationsView = 'invitations.view';
    case InvitationsCreate = 'invitations.create';
    case InvitationsRevoke = 'invitations.revoke';
    case RequestTypesView = 'request_types.view';
    case RequestTypesManage = 'request_types.manage';
    case WorkflowsView = 'workflows.view';
    case WorkflowsManage = 'workflows.manage';
    case RequestsCreate = 'requests.create';
    case RequestsViewOwn = 'requests.view_own';
    case RequestsViewAll = 'requests.view_all';
    case RequestsUpdateOwn = 'requests.update_own';
    case RequestsSubmit = 'requests.submit';
    case RequestsCancelOwn = 'requests.cancel_own';
    case ApprovalsViewAssigned = 'approvals.view_assigned';
    case ApprovalsAct = 'approvals.act';
    case ReportsView = 'reports.view';
    case AuditLogsView = 'audit_logs.view';
}

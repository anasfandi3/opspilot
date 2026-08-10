import type { WorkspaceRole } from './types/member'

const labels: Record<WorkspaceRole, string> = {
  owner: 'Owner',
  admin: 'Admin',
  approver: 'Approver',
  requester: 'Requester',
  auditor: 'Auditor',
}
export const roleLabel = (role: WorkspaceRole | null) => (role ? labels[role] : 'Unassigned')
export const assignableRoles = (actorRole: WorkspaceRole | null) =>
  actorRole === 'owner'
    ? (['admin', 'approver', 'requester', 'auditor'] as const)
    : (['approver', 'requester', 'auditor'] as const)

export const invitationRoleOptions = assignableRoles

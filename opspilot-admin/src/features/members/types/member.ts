export const workspaceRoles = ['owner', 'admin', 'approver', 'requester', 'auditor'] as const
export const invitationRoles = ['admin', 'approver', 'requester', 'auditor'] as const
export type WorkspaceRole = (typeof workspaceRoles)[number]
export type InvitationRole = (typeof invitationRoles)[number]

export interface WorkspaceMember {
  id: number
  name: string
  email: string
  role: WorkspaceRole | null
  roles: WorkspaceRole[]
  joined_at: string
}

export interface WorkspaceInvitation {
  id: number
  workspace_id: number
  invited_by: number
  email: string
  role: InvitationRole
  expires_at: string
  accepted_at: string | null
  revoked_at: string | null
  created_at: string
}

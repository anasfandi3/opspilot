import type { Ref } from 'vue'
import type {
  InvitationRole,
  WorkspaceInvitation,
  WorkspaceMember,
  WorkspaceRole,
} from './types/member'

// WorkspaceInvitationController authorizes both actions through revokeInvitation.
export const invitationActionPermissions = {
  resend: 'invitations.revoke',
  revoke: 'invitations.revoke',
} as const

export function invitationActionVisibility(
  pending: boolean,
  canResend: boolean,
  canRevoke: boolean,
) {
  return { resend: pending && canResend, revoke: pending && canRevoke }
}

export const roleMutationInput = (workspaceId: number, memberId: number, role: WorkspaceRole) => ({
  workspaceId,
  memberId,
  role,
})
export const memberRemovalInput = (workspaceId: number, memberId: number) => ({
  workspaceId,
  memberId,
})
export const invitationMutationInput = (workspaceId: number, invitationId: number) => ({
  workspaceId,
  invitationId,
})
export const invitationCreateInput = (
  workspaceId: number,
  email: string,
  role: InvitationRole,
) => ({ workspaceId, email, role })

export function resetMemberTransientState(state: {
  editing: Ref<WorkspaceMember | null>
  removing: Ref<WorkspaceMember | null>
  role: Ref<WorkspaceRole>
  error: Ref<string>
}) {
  state.editing.value = null
  state.removing.value = null
  state.role.value = 'requester'
  state.error.value = ''
}

export function resetInvitationTransientState(state: {
  inviteOpen: Ref<boolean>
  revoking: Ref<WorkspaceInvitation | null>
  form: {
    email: string
    role: InvitationRole
    emailError: string
    roleError: string
    general: string
  }
}) {
  state.inviteOpen.value = false
  state.revoking.value = null
  state.form.email = ''
  state.form.role = 'requester'
  state.form.emailError = ''
  state.form.roleError = ''
  state.form.general = ''
}

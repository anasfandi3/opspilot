import { describe, expect, it } from 'vitest'
import { reactive, ref } from 'vue'
import { membersKeys, invitationsKeys } from '@/features/members/queries/memberKeys'
import { workspaceKeys } from '@/features/workspaces/queries/workspaceKeys'
import {
  assignableRoles,
  invitationRoleOptions,
  roleLabel,
} from '@/features/members/rolePresentation'
import {
  invitationActionVisibility,
  invitationCreateInput,
  invitationMutationInput,
  resetInvitationTransientState,
  resetMemberTransientState,
  roleMutationInput,
} from '@/features/members/administration'

describe('workspace administration contracts', () => {
  it('uses workspace-scoped query keys', () => {
    expect(workspaceKeys.detail(12)).toEqual(['workspace', 12, 'detail'])
    expect(membersKeys.list(12)).toEqual(['workspace', 12, 'members', 'list'])
    expect(invitationsKeys.list(27)).toEqual(['workspace', 27, 'invitations', 'list'])
  })
  it('matches backend role assignment restrictions', () => {
    expect(assignableRoles('owner')).toContain('admin')
    expect(assignableRoles('admin')).not.toContain('admin')
    expect(assignableRoles('admin')).not.toContain('owner')
    expect(roleLabel('approver')).toBe('Approver')
  })
  it('uses the same owner-only admin restriction for invitations', () => {
    expect(invitationRoleOptions('owner')).toEqual(['admin', 'approver', 'requester', 'auditor'])
    expect(invitationRoleOptions('admin')).toEqual(['approver', 'requester', 'auditor'])
  })
  it('authorizes resend and revoke independently', () => {
    expect(invitationActionVisibility(true, true, false)).toEqual({ resend: true, revoke: false })
    expect(invitationActionVisibility(true, false, true)).toEqual({ resend: false, revoke: true })
    expect(invitationActionVisibility(false, true, true)).toEqual({ resend: false, revoke: false })
  })
  it('captures workspace and target IDs when a mutation is invoked', () => {
    const roleInput = roleMutationInput(1, 10, 'auditor')
    const inviteInput = invitationMutationInput(1, 20)
    const createInput = invitationCreateInput(1, 'person@example.test', 'requester')
    expect(roleInput).toEqual({ workspaceId: 1, memberId: 10, role: 'auditor' })
    expect(inviteInput).toEqual({ workspaceId: 1, invitationId: 20 })
    expect(createInput.workspaceId).toBe(1)
  })
  it('clears an open member target when workspace context changes', () => {
    const member = {
      id: 10,
      name: 'Old member',
      email: 'old@example.test',
      role: 'auditor' as const,
      roles: ['auditor' as const],
      joined_at: '2026-01-01',
    }
    const editing = ref({ ...member })
    const removing = ref({ ...member })
    const role = ref<'auditor' | 'requester'>('auditor')
    const error = ref('Old workspace error')
    resetMemberTransientState({ editing, removing, role, error })
    expect(editing.value).toBeNull()
    expect(removing.value).toBeNull()
    expect(role.value).toBe('requester')
    expect(error.value).toBe('')
  })
  it('clears invitation UI and form state when workspace context changes', () => {
    const inviteOpen = ref(true)
    const revoking = ref({
      id: 20,
      workspace_id: 1,
      invited_by: 1,
      email: 'old@example.test',
      role: 'requester' as const,
      expires_at: '2026-12-01',
      accepted_at: null,
      revoked_at: null,
      created_at: '2026-01-01',
    })
    const form = reactive({
      email: 'stale@example.test',
      role: 'admin' as const,
      emailError: 'error',
      roleError: 'error',
      general: 'error',
    })
    resetInvitationTransientState({ inviteOpen, revoking, form })
    expect(inviteOpen.value).toBe(false)
    expect(revoking.value).toBeNull()
    expect(form).toMatchObject({
      email: '',
      role: 'requester',
      emailError: '',
      roleError: '',
      general: '',
    })
  })
})

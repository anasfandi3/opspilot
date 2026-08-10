import { apiClient, type ApiEnvelope } from '@/lib/api/client'
import type {
  WorkspaceInvitation,
  WorkspaceMember,
  InvitationRole,
  WorkspaceRole,
} from '../types/member'

export const membersApi = {
  list: async (workspaceId: number) =>
    (await apiClient.get<{ data: WorkspaceMember[] }>(`/api/v1/workspaces/${workspaceId}/members`))
      .data,
  updateRole: async (workspaceId: number, userId: number, role: WorkspaceRole) =>
    (
      await apiClient.patch<ApiEnvelope<WorkspaceMember>>(
        `/api/v1/workspaces/${workspaceId}/members/${userId}/role`,
        { role },
      )
    ).data,
  remove: (workspaceId: number, userId: number) =>
    apiClient.delete<ApiEnvelope<Record<string, never>>>(
      `/api/v1/workspaces/${workspaceId}/members/${userId}`,
    ),
}

export const invitationsApi = {
  list: async (workspaceId: number) =>
    (
      await apiClient.get<{ data: WorkspaceInvitation[] }>(
        `/api/v1/workspaces/${workspaceId}/invitations`,
      )
    ).data,
  create: async (workspaceId: number, input: { email: string; role: InvitationRole }) =>
    (
      await apiClient.post<ApiEnvelope<WorkspaceInvitation>>(
        `/api/v1/workspaces/${workspaceId}/invitations`,
        input,
        { csrf: true },
      )
    ).data,
  revoke: (workspaceId: number, invitationId: number) =>
    apiClient.delete<ApiEnvelope<Record<string, never>>>(
      `/api/v1/workspaces/${workspaceId}/invitations/${invitationId}`,
    ),
  resend: async (workspaceId: number, invitationId: number) =>
    (
      await apiClient.post<ApiEnvelope<WorkspaceInvitation>>(
        `/api/v1/workspaces/${workspaceId}/invitations/${invitationId}/resend`,
        undefined,
        { csrf: true },
      )
    ).data,
}

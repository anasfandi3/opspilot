import { workspaceQueryKey } from '@/lib/queryKeys'

export const membersKeys = {
  list: (workspaceId: number) => workspaceQueryKey(workspaceId, 'members', 'list'),
}
export const invitationsKeys = {
  list: (workspaceId: number) => workspaceQueryKey(workspaceId, 'invitations', 'list'),
}

import { workspaceQueryKey } from '@/lib/queryKeys'

export const requestTypeKeys = {
  all: (workspaceId: number) => workspaceQueryKey(workspaceId, 'request-types'),
  list: (workspaceId: number) => workspaceQueryKey(workspaceId, 'request-types', 'list'),
  detail: (workspaceId: number, requestTypeId: number) =>
    workspaceQueryKey(workspaceId, 'request-types', 'detail', requestTypeId),
}

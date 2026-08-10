import { workspaceQueryKey } from '@/lib/queryKeys'

export const workflowKeys = {
  all: (workspaceId: number) => workspaceQueryKey(workspaceId, 'workflows'),
  list: (workspaceId: number) => workspaceQueryKey(workspaceId, 'workflows', 'list'),
  requestType: (workspaceId: number, requestTypeId: number) =>
    workspaceQueryKey(workspaceId, 'workflows', 'request-type', requestTypeId),
  detail: (workspaceId: number, requestTypeId: number, workflowId: number) =>
    workspaceQueryKey(
      workspaceId,
      'workflows',
      'request-type',
      requestTypeId,
      'detail',
      workflowId,
    ),
}

import { workspaceQueryKey } from '@/lib/queryKeys'
export interface RequestFilters {
  page: number
  perPage: number
  status?: string
  requestTypeId?: number
}
export const requestKeys = {
  all: (workspaceId: number) => workspaceQueryKey(workspaceId, 'requests'),
  list: (workspaceId: number, filters: RequestFilters) =>
    workspaceQueryKey(workspaceId, 'requests', 'list', filters),
  detail: (workspaceId: number, requestId: number) =>
    workspaceQueryKey(workspaceId, 'requests', 'detail', requestId),
  catalog: (workspaceId: number) => workspaceQueryKey(workspaceId, 'requests', 'catalog'),
}

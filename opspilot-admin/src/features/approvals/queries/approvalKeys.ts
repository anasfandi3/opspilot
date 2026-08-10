import { workspaceQueryKey } from '@/lib/queryKeys'
export interface ApprovalFilters {
  page: number
  perPage: number
  status?: string
}
export const approvalKeys = {
  all: (workspaceId: number) => workspaceQueryKey(workspaceId, 'approvals'),
  inbox: (workspaceId: number, filters: ApprovalFilters) =>
    workspaceQueryKey(workspaceId, 'approvals', 'inbox', filters),
  detail: (workspaceId: number, approvalId: number) =>
    workspaceQueryKey(workspaceId, 'approvals', 'detail', approvalId),
}

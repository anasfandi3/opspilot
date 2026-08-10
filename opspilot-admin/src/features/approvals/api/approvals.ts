import { apiClient, type ApiEnvelope } from '@/lib/api/client'
import type { ApprovalDetail, PaginatedApprovals } from '../types/approval'
import type { ApprovalFilters } from '../queries/approvalKeys'
import type { RuntimeApproval } from '@/features/requests/types/request'

const root = (workspaceId: number) => `/api/v1/workspaces/${workspaceId}/approvals`
export const approvalsApi = {
  inbox: (workspaceId: number, filters: ApprovalFilters) => {
    const query = new URLSearchParams({
      page: String(filters.page),
      per_page: String(filters.perPage),
    })
    if (filters.status) query.set('status', filters.status)
    return apiClient.get<PaginatedApprovals>(`${root(workspaceId)}?${query}`)
  },
  detail: async (workspaceId: number, approvalId: number) =>
    (await apiClient.get<ApiEnvelope<ApprovalDetail>>(`${root(workspaceId)}/${approvalId}`)).data,
  approve: async (workspaceId: number, approvalId: number) =>
    (
      await apiClient.post<ApiEnvelope<RuntimeApproval>>(
        `${root(workspaceId)}/${approvalId}/approve`,
        undefined,
        { csrf: true },
      )
    ).data,
  reject: async (workspaceId: number, approvalId: number) =>
    (
      await apiClient.post<ApiEnvelope<RuntimeApproval>>(
        `${root(workspaceId)}/${approvalId}/reject`,
        undefined,
        { csrf: true },
      )
    ).data,
}

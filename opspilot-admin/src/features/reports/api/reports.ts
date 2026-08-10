import { apiClient, type ApiEnvelope } from '@/lib/api/client'
import type { ApprovalReport, DashboardData, ReportFilters, RequestReport } from '../types/reports'

const root = (workspaceId: number) => `/api/v1/workspaces/${workspaceId}`
function query(filters: ReportFilters) {
  const params = new URLSearchParams()
  if (filters.from) params.set('from', filters.from)
  if (filters.to) params.set('to', filters.to)
  if (filters.requestTypeId) params.set('request_type_id', String(filters.requestTypeId))
  const value = params.toString()
  return value ? `?${value}` : ''
}
export const reportsApi = {
  dashboard: async (workspaceId: number) =>
    (await apiClient.get<ApiEnvelope<DashboardData>>(`${root(workspaceId)}/dashboard`)).data,
  requests: async (workspaceId: number, filters: ReportFilters) =>
    (
      await apiClient.get<ApiEnvelope<RequestReport>>(
        `${root(workspaceId)}/reports/requests${query(filters)}`,
      )
    ).data,
  approvals: async (workspaceId: number, filters: ReportFilters) =>
    (
      await apiClient.get<ApiEnvelope<ApprovalReport>>(
        `${root(workspaceId)}/reports/approvals${query(filters)}`,
      )
    ).data,
}

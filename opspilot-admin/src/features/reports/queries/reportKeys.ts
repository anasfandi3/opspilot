import { workspaceQueryKey } from '@/lib/queryKeys'
import type { ReportFilters } from '../types/reports'
export const reportKeys = {
  all: (workspaceId: number) => workspaceQueryKey(workspaceId, 'reports'),
  dashboard: (workspaceId: number) => workspaceQueryKey(workspaceId, 'reports', 'dashboard'),
  requests: (workspaceId: number, filters: ReportFilters) =>
    workspaceQueryKey(workspaceId, 'reports', 'requests', filters),
  approvals: (workspaceId: number, filters: ReportFilters) =>
    workspaceQueryKey(workspaceId, 'reports', 'approvals', filters),
}

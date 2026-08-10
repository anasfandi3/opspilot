import type { RequestStatus } from '@/features/requests/types/request'

export interface ReportPeriod {
  from: string
  to: string
  timezone: 'UTC'
}
export interface ReportFilters {
  from?: string
  to?: string
  requestTypeId?: number
}
export interface DashboardData {
  requests: Record<RequestStatus, number> & { total: number }
  approvals: { pending: number }
  request_types: { active: number }
  members: { total: number }
  recent_requests: Array<{
    id: number
    status: RequestStatus
    request_type: { id: number; name: string; slug: string }
    creator: { id: number; name: string }
    submitted_at: string | null
    cancelled_at: string | null
    resolved_at: string | null
    created_at: string
  }>
}
export interface RequestReport {
  period: ReportPeriod
  created: {
    total: number
    current_status: Record<RequestStatus, number>
    by_request_type: Array<{
      request_type: { id: number; name: string; slug: string }
      count: number
    }>
    trend: Array<{ date: string; count: number }>
  }
  lifecycle: {
    submitted: number
    approved: number
    rejected: number
    cancelled: number
    resolution: {
      count: number
      average_hours: number | null
      approved_average_hours: number | null
      rejected_average_hours: number | null
    }
  }
}
export interface ApprovalReport {
  period: ReportPeriod
  current: { pending: number; oldest_pending_activated_at: string | null }
  decisions: {
    total: number
    approved: number
    rejected: number
    average_decision_hours: number | null
    approved_average_hours: number | null
    rejected_average_hours: number | null
    trend: Array<{ date: string; approved: number; rejected: number; total: number }>
    by_step: Array<{
      workflow_step: { id: number; name: string }
      approved: number
      rejected: number
      total: number
    }>
  }
}

import type { RequestTypeField } from '@/features/request-types/types/requestType'

export type RequestStatus = 'draft' | 'submitted' | 'approved' | 'rejected' | 'cancelled'
export type RequestValue = string | number | boolean | string[] | null
export type RequestPayload = Record<string, RequestValue>
export interface RequestCatalogItem {
  id: number
  name: string
  slug: string
  description: string | null
  fields: RequestTypeField[]
}
export interface RequestSummary {
  id: number
  status: RequestStatus
  payload: RequestPayload
  request_type: { id: number; name: string; slug: string }
  workflow: { id: number; version: number; name: string } | null
  creator: { id: number; name: string; email: string }
  submitted_at: string | null
  cancelled_at: string | null
  resolved_at: string | null
  created_at: string
  updated_at: string
}
export type ApprovalStatus =
  'waiting' | 'pending' | 'approved' | 'rejected' | 'skipped' | 'cancelled'
export interface RuntimeApproval {
  id: number
  position: number
  status: ApprovalStatus
  workflow_step: { id: number; name: string }
  approver_type: string
  approver_role: string | null
  assignees: { id: number; name: string }[]
  decided_by: { id: number; name: string } | null
  activated_at: string | null
  decided_at: string | null
}
export interface RequestDetail extends RequestSummary {
  definition_snapshot: {
    request_type?: { id: number; name: string; slug: string }
    fields?: RequestTypeField[]
  } | null
  approvals: RuntimeApproval[]
}
export interface PaginatedRequests {
  data: RequestSummary[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

import type {
  ApprovalStatus,
  RequestDetail,
  RuntimeApproval,
} from '@/features/requests/types/request'

export interface ApprovalInboxItem {
  id: number
  status: ApprovalStatus
  position: number
  workflow_step: { id: number; name: string }
  request: {
    id: number
    status: RequestDetail['status']
    request_type: { id: number; name: string; slug: string }
    creator: { id: number; name: string; email: string }
  }
  activated_at: string | null
  decided_at: string | null
}

export interface ApprovalDetail extends RuntimeApproval {
  request: RequestDetail
}

export interface PaginatedApprovals {
  data: ApprovalInboxItem[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

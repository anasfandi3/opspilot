import type { RequestStatus, ApprovalStatus } from './types/request'
export const requestStatusLabels: Record<RequestStatus, string> = {
  draft: 'Draft',
  submitted: 'Submitted',
  approved: 'Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}
export const approvalStatusLabels: Record<ApprovalStatus, string> = {
  waiting: 'Waiting',
  pending: 'Pending',
  approved: 'Approved',
  rejected: 'Rejected',
  skipped: 'Skipped',
  cancelled: 'Cancelled',
}
export function requestStatusVariant(status: RequestStatus) {
  return status === 'approved'
    ? 'default'
    : status === 'rejected' || status === 'cancelled'
      ? 'destructive'
      : 'secondary'
}

import { ApiError } from '@/lib/api/errors'
import type { ApprovalStatus } from '@/features/requests/types/request'

export function canActOnApproval(status: ApprovalStatus, can: (permission: string) => boolean) {
  return status === 'pending' && can('approvals.act')
}

export function isStaleApprovalError(error: unknown) {
  return (
    error instanceof ApiError &&
    (error.kind === 'conflict' ||
      error.kind === 'forbidden' ||
      (error.kind === 'validation' && 'approval' in error.fieldErrors))
  )
}

export function approvalWorkspaceDestination(routeName: unknown) {
  return routeName === 'approvals' ? null : '/approvals'
}

export function canApplyApprovalResult(mutationWorkspaceId: number, currentWorkspaceId: number) {
  return mutationWorkspaceId === currentWorkspaceId
}

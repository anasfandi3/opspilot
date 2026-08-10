import { describe, expect, it } from 'vitest'
import { ApiError } from '@/lib/api/errors'
import { approvalKeys } from '@/features/approvals/queries/approvalKeys'
import {
  approvalWorkspaceDestination,
  canActOnApproval,
  canApplyApprovalResult,
  isStaleApprovalError,
} from '@/features/approvals/approvalActions'
import { approvalStatusLabels } from '@/features/requests/requestStatus'

describe('approval inbox foundation', () => {
  it('isolates inbox and detail keys by workspace and filters', () => {
    expect(approvalKeys.inbox(1, { page: 1, perPage: 20 })).not.toEqual(
      approvalKeys.inbox(2, { page: 1, perPage: 20 }),
    )
    expect(approvalKeys.detail(1, 7)).not.toEqual(approvalKeys.inbox(1, { page: 1, perPage: 20 }))
  })

  it('allows only pending approvals with approvals.act', () => {
    const allowed = (permission: string) => permission === 'approvals.act'
    expect(canActOnApproval('pending', allowed)).toBe(true)
    for (const status of ['waiting', 'approved', 'rejected', 'skipped', 'cancelled'] as const)
      expect(canActOnApproval(status, allowed)).toBe(false)
    expect(canActOnApproval('pending', () => false)).toBe(false)
  })

  it('uses exact approval status presentation', () => {
    expect(approvalStatusLabels).toEqual({
      waiting: 'Waiting',
      pending: 'Pending',
      approved: 'Approved',
      rejected: 'Rejected',
      skipped: 'Skipped',
      cancelled: 'Cancelled',
    })
  })

  it('recognizes backend stale decision responses without treating network errors as stale', () => {
    expect(isStaleApprovalError(new ApiError('forbidden', 'Forbidden', 403))).toBe(true)
    expect(
      isStaleApprovalError(
        new ApiError('validation', 'Invalid', 422, {
          approval: ['This approval is no longer actionable.'],
        }),
      ),
    ).toBe(true)
    expect(isStaleApprovalError(new ApiError('conflict', 'Changed', 409))).toBe(true)
    expect(isStaleApprovalError(new ApiError('network', 'Offline'))).toBe(false)
  })

  it('resets detail routes and rejects late cross-workspace decisions', () => {
    expect(approvalWorkspaceDestination('approvals')).toBeNull()
    expect(approvalWorkspaceDestination('approvals-detail')).toBe('/approvals')
    expect(canApplyApprovalResult(1, 2)).toBe(false)
    expect(canApplyApprovalResult(2, 2)).toBe(true)
  })
})

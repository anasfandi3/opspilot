import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { notificationKeys } from '@/features/notifications/queries/notificationKeys'
import {
  canApplyNotificationResult,
  isUnread,
  notificationBody,
  notificationTitle,
  unreadBadge,
} from '@/features/notifications/notificationPresentation'
import {
  notificationDestination,
  notificationNavigationPlan,
  workspaceNavigationDecision,
} from '@/features/notifications/notificationNavigation'
import type { OpsNotification } from '@/features/notifications/types/notification'
import { useNotificationStore } from '@/stores/notifications'

const makeNotification = (overrides: Partial<OpsNotification> = {}): OpsNotification => ({
  id: '31a4516c-b1a4-4d73-a33e-24be27caf455',
  event: 'request_approved',
  message: 'Purchase Request #51 was approved.',
  workspace: { id: 4, name: 'Acme Operations' },
  request: { id: 51, request_type: { id: 6, name: 'Purchase Request' } },
  approval: null,
  actor: null,
  comment: null,
  attachment: null,
  read_at: null,
  created_at: '2026-08-10T00:00:00Z',
  ...overrides,
})

describe('notifications', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('uses global keys while isolating list page and filter state', () => {
    expect(notificationKeys.recent()).toEqual(['notifications', 'recent'])
    expect(notificationKeys.unreadCount()).toEqual(['notifications', 'unread-count'])
    expect(notificationKeys.list(1, 'all')).not.toEqual(notificationKeys.list(2, 'all'))
    expect(notificationKeys.list(1, 'all')).not.toEqual(notificationKeys.list(1, 'unread'))
    expect(notificationKeys.recent()).not.toContain('workspace')
  })

  it.each([
    ['approval_assigned', 'Approval assigned'],
    ['request_approved', 'Request approved'],
    ['request_rejected', 'Request rejected'],
    ['request_cancelled', 'Request cancelled'],
    ['comment_added', 'New comment'],
    ['attachment_uploaded', 'New attachment'],
  ])('presents backend event %s', (event, title) => {
    expect(notificationTitle(makeNotification({ event }))).toBe(title)
  })

  it('uses safe presentation fallbacks and exact unread state', () => {
    const unknown = makeNotification({ event: 'future_event', message: '  ' })
    expect(notificationTitle(unknown)).toBe('Notification')
    expect(notificationBody(unknown)).toBe('You have a new notification.')
    expect(isUnread(unknown)).toBe(true)
    expect(isUnread(makeNotification({ read_at: '2026-08-10T01:00:00Z' }))).toBe(false)
    expect(unreadBadge(100)).toBe('99+')
  })

  it('maps approval assignments and request events to registered routes', () => {
    expect(
      notificationDestination(
        makeNotification({
          event: 'approval_assigned',
          approval: { id: 9, position: 1, workflow_step_name: 'Manager' },
        }),
      ),
    ).toBe('/approvals/9')
    expect(notificationDestination(makeNotification())).toBe('/requests/51')
    expect(
      notificationDestination(makeNotification({ request: null, event: 'future_event' })),
    ).toBeNull()
  })

  it('decides workspace-aware navigation and protects results by user session', () => {
    expect(workspaceNavigationDecision(4, 4)).toBe('same')
    expect(workspaceNavigationDecision(5, 4)).toBe('switch')
    expect(workspaceNavigationDecision(null, 4)).toBe('unavailable')
    expect(canApplyNotificationResult(7, 7)).toBe(true)
    expect(canApplyNotificationResult(7, 8)).toBe(false)
    expect(canApplyNotificationResult(7, null)).toBe(false)
  })

  it('creates one safe navigation plan for bell and inbox', () => {
    expect(notificationNavigationPlan(makeNotification(), 4)).toEqual({
      kind: 'same',
      destination: '/requests/51',
    })
    expect(notificationNavigationPlan(makeNotification(), 2)).toEqual({
      kind: 'switch',
      destination: '/requests/51',
      workspaceId: 4,
    })
    expect(notificationNavigationPlan(makeNotification({ workspace: null }), 2)).toEqual({
      kind: 'unavailable',
    })
  })

  it('synchronizes only authoritative counts and resets session summary state', () => {
    const store = useNotificationStore()
    store.synchronizeUnreadCount(7)
    expect(store.unreadCount).toBe(7)
    store.synchronizeUnreadCount(-2)
    expect(store.unreadCount).toBe(0)
    store.reset()
    expect(store.unreadCount).toBeNull()
  })
})

import type { OpsNotification } from './types/notification'

const titles: Record<string, string> = {
  approval_assigned: 'Approval assigned',
  request_approved: 'Request approved',
  request_rejected: 'Request rejected',
  request_cancelled: 'Request cancelled',
  comment_added: 'New comment',
  attachment_uploaded: 'New attachment',
}

export function notificationTitle(notification: OpsNotification) {
  return titles[notification.event ?? ''] ?? 'Notification'
}

export function notificationBody(notification: OpsNotification) {
  return notification.message?.trim() || 'You have a new notification.'
}

export function isUnread(notification: OpsNotification) {
  return notification.read_at === null
}

export function unreadBadge(count: number) {
  return count > 99 ? '99+' : String(count)
}

export function canApplyNotificationResult(
  sourceUserId: number | null | undefined,
  currentUserId: number | null | undefined,
) {
  return sourceUserId != null && sourceUserId === currentUserId
}

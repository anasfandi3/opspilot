import type { NotificationStatus } from '../types/notification'

export const notificationKeys = {
  all: () => ['notifications'] as const,
  list: (page: number, status: NotificationStatus) =>
    ['notifications', 'list', { page, status }] as const,
  recent: () => ['notifications', 'recent'] as const,
  unreadCount: () => ['notifications', 'unread-count'] as const,
}

import { apiClient, type ApiEnvelope } from '@/lib/api/client'
import type { NotificationPage, NotificationStatus, OpsNotification } from '../types/notification'

export const notificationApi = {
  list: (page: number, perPage: number, status: NotificationStatus = 'all') =>
    apiClient.get<NotificationPage>(
      `/api/v1/notifications?page=${page}&per_page=${perPage}&status=${status}`,
    ),
  unreadCount: async () =>
    (
      await apiClient.get<ApiEnvelope<{ unread_count: number }>>(
        '/api/v1/notifications/unread-count',
      )
    ).data.unread_count,
  markRead: async (id: string) =>
    (await apiClient.patch<ApiEnvelope<OpsNotification>>(`/api/v1/notifications/${id}/read`, {}))
      .data,
  markUnread: async (id: string) =>
    (await apiClient.patch<ApiEnvelope<OpsNotification>>(`/api/v1/notifications/${id}/unread`, {}))
      .data,
  markAllRead: () =>
    apiClient.post<ApiEnvelope<{ affected: number }>>('/api/v1/notifications/read-all', undefined, {
      csrf: true,
    }),
}

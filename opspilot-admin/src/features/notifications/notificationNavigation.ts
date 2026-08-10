import type { OpsNotification } from './types/notification'

export function notificationDestination(notification: OpsNotification): string | null {
  if (notification.event === 'approval_assigned' && notification.approval)
    return `/approvals/${notification.approval.id}`
  if (notification.request) return `/requests/${notification.request.id}`
  return null
}

export type WorkspaceNavigationDecision = 'same' | 'switch' | 'unavailable'
export function workspaceNavigationDecision(
  notificationWorkspaceId: number | null | undefined,
  currentWorkspaceId: number | null | undefined,
): WorkspaceNavigationDecision {
  if (!notificationWorkspaceId) return 'unavailable'
  return notificationWorkspaceId === currentWorkspaceId ? 'same' : 'switch'
}

export type NotificationNavigationPlan =
  | { kind: 'unavailable' }
  | { kind: 'same'; destination: string }
  | { kind: 'switch'; destination: string; workspaceId: number }

export function notificationNavigationPlan(
  notification: OpsNotification,
  currentWorkspaceId: number | null | undefined,
): NotificationNavigationPlan {
  const destination = notificationDestination(notification)
  const decision = workspaceNavigationDecision(notification.workspace?.id, currentWorkspaceId)
  if (!destination || decision === 'unavailable') return { kind: 'unavailable' }
  if (decision === 'switch')
    return { kind: 'switch', destination, workspaceId: notification.workspace!.id }
  return { kind: 'same', destination }
}

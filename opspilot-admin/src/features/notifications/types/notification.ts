export type NotificationEvent =
  | 'approval_assigned'
  | 'request_approved'
  | 'request_rejected'
  | 'request_cancelled'
  | 'comment_added'
  | 'attachment_uploaded'

export interface OpsNotification {
  id: string
  event: NotificationEvent | string | null
  message: string | null
  workspace: { id: number; name: string } | null
  request: { id: number; request_type: { id: number; name: string } } | null
  approval: { id: number; position: number; workflow_step_name: string } | null
  actor: { id: number; name: string } | null
  comment: { id: number } | null
  attachment: {
    id: number
    original_name: string
    mime_type: string
    size_bytes: number
  } | null
  read_at: string | null
  created_at: string
}

export interface NotificationPage {
  data: OpsNotification[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type NotificationStatus = 'all' | 'unread' | 'read'

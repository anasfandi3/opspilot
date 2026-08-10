export interface CollaborationPage<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}
export interface RequestComment {
  id: number
  body: string
  author: { id: number; name: string }
  created_at: string
}
export interface RequestAttachment {
  id: number
  original_name: string
  mime_type: string
  size_bytes: number
  uploader: { id: number; name: string }
  created_at: string
}
export type ActivityType =
  | 'request_created'
  | 'request_submitted'
  | 'request_cancelled'
  | 'request_approved'
  | 'request_rejected'
  | 'approval_activated'
  | 'approval_approved'
  | 'approval_rejected'
  | 'comment_added'
  | 'attachment_uploaded'
export interface RequestActivity {
  id: number
  type: ActivityType | string
  actor: { id: number; name: string } | null
  metadata: Record<string, unknown> | null
  comment: RequestComment | null
  attachment: RequestAttachment | null
  approval: {
    id: number
    status: string
    position: number
    workflow_step: { id: number; name: string }
  } | null
  created_at: string
}

import type { RequestActivity } from './types'
export const attachmentExtensions = [
  'pdf',
  'txt',
  'csv',
  'png',
  'jpg',
  'jpeg',
  'webp',
  'doc',
  'docx',
  'xls',
  'xlsx',
]
export const attachmentMaxBytes = 10 * 1024 * 1024
export function formatFileSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 ** 2).toFixed(1)} MB`
}
export function validateUpload(file?: File | null) {
  if (!file) return 'Choose a file.'
  if (file.size > attachmentMaxBytes) return 'File must not exceed 10 MB.'
  const extension = file.name.split('.').pop()?.toLowerCase() ?? ''
  if (!attachmentExtensions.includes(extension)) return 'This file type is not supported.'
  if (file.name.length > 255) return 'File name must not exceed 255 characters.'
  return ''
}
export function commentPayload(body: string) {
  return { body: body.trim() }
}
export function activityText(activity: RequestActivity) {
  const actor = activity.actor?.name ?? 'System'
  const step =
    activity.approval?.workflow_step.name ??
    String(activity.metadata?.workflow_step_name ?? 'approval step')
  const labels: Record<string, string> = {
    request_created: `${actor} created the request`,
    request_submitted: `${actor} submitted the request`,
    request_cancelled: `${actor} cancelled the request`,
    request_approved: `${actor} completed the request as approved`,
    request_rejected: `${actor} completed the request as rejected`,
    approval_activated: `${step} became pending`,
    approval_approved: `${actor} approved ${step}`,
    approval_rejected: `${actor} rejected ${step}`,
    comment_added: `${actor} added a comment`,
    attachment_uploaded: `${actor} uploaded ${activity.attachment?.original_name ?? 'an attachment'}`,
  }
  return labels[activity.type] ?? `${actor}: ${activity.type.replace(/_/g, ' ')}`
}
export function canApplyCollaborationResult(sourceWorkspaceId: number, currentWorkspaceId: number) {
  return sourceWorkspaceId === currentWorkspaceId
}
export function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename || 'attachment'
  anchor.click()
  URL.revokeObjectURL(url)
}

import { apiClient, type ApiEnvelope } from '@/lib/api/client'
import type { CollaborationPage, RequestActivity, RequestAttachment, RequestComment } from './types'
const root = (workspaceId: number, requestId: number) =>
  `/api/v1/workspaces/${workspaceId}/requests/${requestId}`
export const collaborationApi = {
  comments: (workspaceId: number, requestId: number, page = 1) =>
    apiClient.get<CollaborationPage<RequestComment>>(
      `${root(workspaceId, requestId)}/comments?per_page=20&page=${page}`,
    ),
  addComment: async (workspaceId: number, requestId: number, body: string) =>
    (
      await apiClient.post<ApiEnvelope<RequestComment>>(
        `${root(workspaceId, requestId)}/comments`,
        { body },
        { csrf: true },
      )
    ).data,
  attachments: (workspaceId: number, requestId: number, page = 1) =>
    apiClient.get<CollaborationPage<RequestAttachment>>(
      `${root(workspaceId, requestId)}/attachments?per_page=20&page=${page}`,
    ),
  upload: async (workspaceId: number, requestId: number, file: File) => {
    const body = new FormData()
    body.append('file', file)
    return (
      await apiClient.upload<ApiEnvelope<RequestAttachment>>(
        `${root(workspaceId, requestId)}/attachments`,
        body,
      )
    ).data
  },
  download: (workspaceId: number, requestId: number, attachmentId: number) =>
    apiClient.download(`${root(workspaceId, requestId)}/attachments/${attachmentId}/download`),
  activity: (workspaceId: number, requestId: number, page = 1) =>
    apiClient.get<CollaborationPage<RequestActivity>>(
      `${root(workspaceId, requestId)}/activity?per_page=50&page=${page}`,
    ),
}

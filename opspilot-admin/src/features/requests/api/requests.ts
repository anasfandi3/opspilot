import { apiClient, type ApiEnvelope } from '@/lib/api/client'
import type {
  PaginatedRequests,
  RequestCatalogItem,
  RequestDetail,
  RequestPayload,
} from '../types/request'
import type { RequestFilters } from '../queries/requestKeys'
const root = (workspaceId: number) => `/api/v1/workspaces/${workspaceId}/requests`
export const requestsApi = {
  catalog: async (workspaceId: number) =>
    (
      await apiClient.get<{ data: RequestCatalogItem[] }>(
        `/api/v1/workspaces/${workspaceId}/request-catalog`,
      )
    ).data,
  list: (workspaceId: number, filters: RequestFilters) => {
    const query = new URLSearchParams({
      page: String(filters.page),
      per_page: String(filters.perPage),
    })
    if (filters.status) query.set('status', filters.status)
    if (filters.requestTypeId) query.set('request_type_id', String(filters.requestTypeId))
    return apiClient.get<PaginatedRequests>(`${root(workspaceId)}?${query}`)
  },
  detail: async (workspaceId: number, id: number) =>
    (await apiClient.get<ApiEnvelope<RequestDetail>>(`${root(workspaceId)}/${id}`)).data,
  create: async (workspaceId: number, requestTypeId: number, payload: RequestPayload) =>
    (
      await apiClient.post<ApiEnvelope<RequestDetail>>(
        `/api/v1/workspaces/${workspaceId}/request-types/${requestTypeId}/requests`,
        { payload },
        { csrf: true },
      )
    ).data,
  update: async (workspaceId: number, id: number, payload: RequestPayload) =>
    (await apiClient.patch<ApiEnvelope<RequestDetail>>(`${root(workspaceId)}/${id}`, { payload }))
      .data,
  submit: async (workspaceId: number, id: number) =>
    (
      await apiClient.post<ApiEnvelope<RequestDetail>>(
        `${root(workspaceId)}/${id}/submit`,
        undefined,
        { csrf: true },
      )
    ).data,
  cancel: async (workspaceId: number, id: number) =>
    (
      await apiClient.post<ApiEnvelope<RequestDetail>>(
        `${root(workspaceId)}/${id}/cancel`,
        undefined,
        { csrf: true },
      )
    ).data,
}

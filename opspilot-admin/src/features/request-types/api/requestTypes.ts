import { apiClient, type ApiEnvelope } from '@/lib/api/client'
import type {
  RequestType,
  RequestTypeField,
  RequestTypeFieldInput,
  RequestTypeInput,
} from '../types/requestType'

function root(workspaceId: number) {
  return `/api/v1/workspaces/${workspaceId}/request-types`
}
export const requestTypesApi = {
  list: async (workspaceId: number) =>
    (await apiClient.get<{ data: RequestType[] }>(root(workspaceId))).data,
  detail: async (workspaceId: number, requestTypeId: number) =>
    (await apiClient.get<ApiEnvelope<RequestType>>(`${root(workspaceId)}/${requestTypeId}`)).data,
  create: async (workspaceId: number, input: RequestTypeInput) =>
    (await apiClient.post<ApiEnvelope<RequestType>>(root(workspaceId), input, { csrf: true })).data,
  update: async (workspaceId: number, requestTypeId: number, input: RequestTypeInput) =>
    (
      await apiClient.patch<ApiEnvelope<RequestType>>(
        `${root(workspaceId)}/${requestTypeId}`,
        input,
      )
    ).data,
  createField: async (workspaceId: number, requestTypeId: number, input: RequestTypeFieldInput) =>
    (
      await apiClient.post<ApiEnvelope<RequestTypeField>>(
        `${root(workspaceId)}/${requestTypeId}/fields`,
        input,
        { csrf: true },
      )
    ).data,
  updateField: async (
    workspaceId: number,
    requestTypeId: number,
    fieldId: number,
    input: Omit<RequestTypeFieldInput, 'key' | 'type'>,
  ) =>
    (
      await apiClient.patch<ApiEnvelope<RequestTypeField>>(
        `${root(workspaceId)}/${requestTypeId}/fields/${fieldId}`,
        input,
      )
    ).data,
  deleteField: (workspaceId: number, requestTypeId: number, fieldId: number) =>
    apiClient.delete(`${root(workspaceId)}/${requestTypeId}/fields/${fieldId}`),
  reorderFields: async (workspaceId: number, requestTypeId: number, fieldIds: number[]) =>
    (
      await apiClient.post<ApiEnvelope<RequestTypeField[]>>(
        `${root(workspaceId)}/${requestTypeId}/fields/reorder`,
        { field_ids: fieldIds },
        { csrf: true },
      )
    ).data,
}

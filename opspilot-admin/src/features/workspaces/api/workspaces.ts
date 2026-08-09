import { apiClient, type ApiEnvelope } from '@/lib/api/client'
import type { Workspace } from '../types/workspace'
export const workspaceApi = {
  list: async () => (await apiClient.get<{ data: Workspace[] }>('/api/v1/workspaces')).data,
  switchTo: async (id: number) =>
    (
      await apiClient.post<ApiEnvelope<Workspace>>(`/api/v1/workspaces/${id}/switch`, undefined, {
        csrf: true,
      })
    ).data,
}

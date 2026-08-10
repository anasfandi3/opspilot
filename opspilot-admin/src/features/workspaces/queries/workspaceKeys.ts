import { workspaceQueryKey } from '@/lib/queryKeys'

export const workspaceKeys = {
  detail: (workspaceId: number) => workspaceQueryKey(workspaceId, 'detail'),
}

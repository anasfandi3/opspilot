import type { QueryKey } from '@tanstack/vue-query'

export function workspaceQueryKey(workspaceId: number, ...parts: readonly unknown[]) {
  return ['workspace', workspaceId, ...parts] as const
}

export function isWorkspaceQueryKey(queryKey: QueryKey, workspaceId: number) {
  return queryKey[0] === 'workspace' && queryKey[1] === workspaceId
}

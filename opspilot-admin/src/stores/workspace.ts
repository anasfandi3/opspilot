import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { workspaceApi } from '@/features/workspaces/api/workspaces'
import type { Workspace } from '@/features/workspaces/types/workspace'
import { queryClient } from '@/lib/queryClient'
import { isWorkspaceQueryKey } from '@/lib/queryKeys'

export const WORKSPACE_STORAGE_KEY = 'opspilot-current-workspace'
export function chooseWorkspace(workspaces: Workspace[], persistedId: number | null) {
  return workspaces.find((item) => item.id === persistedId) ?? workspaces[0] ?? null
}
export const useWorkspaceStore = defineStore('workspace', () => {
  const workspaces = ref<Workspace[]>([])
  const currentWorkspace = ref<Workspace | null>(null)
  const initialized = ref(false)
  const switching = ref(false)
  const currentWorkspaceId = computed(() => currentWorkspace.value?.id ?? null)
  async function initialize() {
    const list = await workspaceApi.list()
    workspaces.value = list
    const raw = localStorage.getItem(WORKSPACE_STORAGE_KEY)
    const persisted = raw && Number.isInteger(Number(raw)) ? Number(raw) : null
    const selected = chooseWorkspace(list, persisted)
    if (selected) await selectWorkspace(selected.id)
    else {
      currentWorkspace.value = null
      localStorage.removeItem(WORKSPACE_STORAGE_KEY)
    }
    initialized.value = true
  }
  async function selectWorkspace(id: number) {
    const target = workspaces.value.find((item) => item.id === id)
    if (switching.value || !target || target.id === currentWorkspace.value?.id) return
    switching.value = true
    const previousId = currentWorkspace.value?.id
    try {
      currentWorkspace.value = await workspaceApi.switchTo(id)
      localStorage.setItem(WORKSPACE_STORAGE_KEY, String(id))
      if (previousId) {
        await queryClient.cancelQueries({
          predicate: (query) => isWorkspaceQueryKey(query.queryKey, previousId),
        })
        queryClient.removeQueries({
          predicate: (query) => isWorkspaceQueryKey(query.queryKey, previousId),
        })
      }
    } finally {
      switching.value = false
    }
  }
  function reset() {
    workspaces.value = []
    currentWorkspace.value = null
    initialized.value = false
    switching.value = false
    localStorage.removeItem(WORKSPACE_STORAGE_KEY)
  }
  return {
    workspaces,
    currentWorkspace,
    currentWorkspaceId,
    initialized,
    switching,
    initialize,
    selectWorkspace,
    reset,
  }
})

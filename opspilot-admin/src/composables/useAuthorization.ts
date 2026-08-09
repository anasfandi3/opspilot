import { computed } from 'vue'
import { useWorkspaceStore } from '@/stores/workspace'
export function hasPermission(permissions: readonly string[], permission: string) {
  return permissions.includes(permission)
}
export function useAuthorization() {
  const store = useWorkspaceStore()
  const permissions = computed(() => store.currentWorkspace?.permissions ?? [])
  return {
    permissions,
    can: (permission: string) => hasPermission(permissions.value, permission),
    canAny: (required: string[]) => required.some((item) => hasPermission(permissions.value, item)),
    canAll: (required: string[]) =>
      required.every((item) => hasPermission(permissions.value, item)),
  }
}

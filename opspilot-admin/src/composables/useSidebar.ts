import { useStorage } from '@vueuse/core'

export function useSidebar() {
  const collapsed = useStorage('opspilot-sidebar-collapsed', false)
  return { collapsed }
}

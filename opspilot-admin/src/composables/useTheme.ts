import { useColorMode } from '@vueuse/core'

export type ThemeMode = 'light' | 'dark' | 'auto'

export function useTheme() {
  const mode = useColorMode({ storageKey: 'opspilot-theme', emitAuto: true })
  return { mode }
}

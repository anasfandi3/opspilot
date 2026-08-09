import type { Pinia } from 'pinia'
import type { Router } from 'vue-router'
import { configureSessionExpiryHandler } from '@/lib/api/sessionExpiry'
import { useAuthStore } from '@/stores/auth'
import { safeRedirect } from '@/router/home'

export function installSessionExpiryHandler(router: Router, pinia: Pinia) {
  configureSessionExpiryHandler(async () => {
    const auth = useAuthStore(pinia)
    if (!auth.authenticated) return

    const currentRoute = router.currentRoute.value
    const redirect = currentRoute.name === 'login' ? null : safeRedirect(currentRoute.fullPath)
    auth.expireSession()

    if (currentRoute.name !== 'login') {
      await router.replace({ name: 'login', query: redirect ? { redirect } : {} })
    }
  })
}

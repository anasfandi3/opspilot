import type { Pinia } from 'pinia'
import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'
import { hasPermission } from '@/composables/useAuthorization'
import { resolveAccessibleHome } from './home'
import { canAccessRoute, routeNeedsWorkspace } from './authorization'

export function installGuards(router: Router, pinia: Pinia) {
  router.beforeEach(async (to) => {
    const auth = useAuthStore(pinia)
    const workspace = useWorkspaceStore(pinia)
    const needsWorkspace = routeNeedsWorkspace(to.meta)
    const needsAuthState = Boolean(to.meta.requiresAuth || to.meta.guestOnly || needsWorkspace)
    if (!needsAuthState) return
    await auth.bootstrap()
    if (auth.authenticated && (needsWorkspace || to.meta.guestOnly) && !workspace.initialized)
      await workspace.initialize()
    if (to.meta.guestOnly && auth.authenticated)
      return resolveAccessibleHome(router, (permission) =>
        hasPermission(workspace.currentWorkspace?.permissions ?? [], permission),
      )
    if (to.meta.requiresAuth && !auth.authenticated)
      return { name: 'login', query: { redirect: to.fullPath } }
    const permissions = workspace.currentWorkspace?.permissions ?? []
    if (!canAccessRoute(to.meta, (permission) => hasPermission(permissions, permission)))
      return { name: 'forbidden' }
  })
}

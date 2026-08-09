import type { Router } from 'vue-router'
import { canAccessRoute, type PermissionCheck } from './authorization'
export function safeRedirect(value: unknown): string | null {
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//')) return null
  return value
}
export function resolveAccessibleHome(router: Router, can: PermissionCheck) {
  const candidates = ['dashboard', 'requests', 'approvals', 'home']
  for (const name of candidates) {
    if (!router.hasRoute(name)) continue
    const route = router.resolve({ name })
    if (canAccessRoute(route.meta, can)) return route.fullPath
  }
  return '/home'
}

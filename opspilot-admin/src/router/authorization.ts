import type { RouteMeta } from 'vue-router'

export type PermissionCheck = (permission: string) => boolean

export function routeNeedsWorkspace(meta: RouteMeta) {
  return Boolean(
    meta.requiresWorkspace ||
    typeof meta.permission === 'string' ||
    Array.isArray(meta.anyPermissions),
  )
}

export function canAccessRoute(meta: RouteMeta, can: PermissionCheck) {
  if (typeof meta.permission === 'string' && !can(meta.permission)) return false
  if (
    Array.isArray(meta.anyPermissions) &&
    !meta.anyPermissions.some((permission) => typeof permission === 'string' && can(permission))
  )
    return false
  return true
}

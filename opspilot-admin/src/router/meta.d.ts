import 'vue-router'
export {}
declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
    requiresWorkspace?: boolean
    permission?: string
    anyPermissions?: string[]
  }
}

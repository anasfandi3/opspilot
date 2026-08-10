import type { RouteLocationNormalizedLoadedGeneric, RouteRecordNameGeneric } from 'vue-router'

const titles: Record<string, string> = {
  login: 'Sign in',
  dashboard: 'Dashboard',
  requests: 'Requests',
  'requests-create': 'Create request',
  'requests-detail': 'Request details',
  'requests-edit': 'Edit request',
  approvals: 'Approvals',
  'approvals-detail': 'Approval details',
  'request-types': 'Request types',
  'request-types-create': 'Create request type',
  'request-types-detail': 'Request type details',
  'request-types-edit': 'Edit request type',
  workflows: 'Workflows',
  'workflows-create': 'Create workflow',
  'workflows-detail': 'Workflow details',
  'workflows-edit': 'Edit workflow',
  'workspace-settings': 'Workspace settings',
  members: 'Members',
  invitations: 'Invitations',
  notifications: 'Notifications',
  'request-report': 'Request report',
  'approval-report': 'Approval report',
  home: 'Home',
  forbidden: 'Access denied',
  'not-found': 'Page not found',
  ui: 'UI foundation',
}

export function routeDocumentTitle(name: RouteRecordNameGeneric) {
  const title = typeof name === 'string' ? titles[name] : undefined
  return title ? `${title} · OpsPilot` : 'OpsPilot'
}

export function updateDocumentTitle(route: Pick<RouteLocationNormalizedLoadedGeneric, 'name'>) {
  document.title = routeDocumentTitle(route.name)
}

import type { RouteRecordRaw } from 'vue-router'
export const workspaceRoutes: RouteRecordRaw[] = [
  {
    path: '/settings/workspace',
    name: 'workspace-settings',
    component: () => import('./views/WorkspaceSettingsView.vue'),
    meta: { requiresAuth: true, requiresWorkspace: true, permission: 'workspace.view' },
  },
]

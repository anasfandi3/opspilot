import type { RouteRecordRaw } from 'vue-router'
const view = { requiresAuth: true, requiresWorkspace: true, permission: 'workflows.view' } as const
const manage = {
  requiresAuth: true,
  requiresWorkspace: true,
  permission: 'workflows.manage',
} as const
export const workflowRoutes: RouteRecordRaw[] = [
  {
    path: '/workflows',
    name: 'workflows',
    component: () => import('./views/WorkflowListView.vue'),
    meta: view,
  },
  {
    path: '/workflows/create',
    name: 'workflows-create',
    component: () => import('./views/WorkflowFormView.vue'),
    meta: manage,
  },
  {
    path: '/workflows/:id',
    name: 'workflows-detail',
    component: () => import('./views/WorkflowDetailView.vue'),
    meta: view,
  },
  {
    path: '/workflows/:id/edit',
    name: 'workflows-edit',
    component: () => import('./views/WorkflowFormView.vue'),
    meta: manage,
  },
]

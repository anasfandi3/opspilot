import type { RouteRecordRaw } from 'vue-router'
const list = {
  requiresAuth: true,
  requiresWorkspace: true,
  anyPermissions: ['requests.view_own', 'requests.view_all'],
}
const detail = {
  requiresAuth: true,
  requiresWorkspace: true,
  anyPermissions: ['requests.view_own', 'requests.view_all', 'approvals.view_assigned'],
}
export const requestRoutes: RouteRecordRaw[] = [
  {
    path: '/requests',
    name: 'requests',
    component: () => import('./views/RequestListView.vue'),
    meta: list,
  },
  {
    path: '/requests/create',
    name: 'requests-create',
    component: () => import('./views/RequestFormView.vue'),
    meta: { requiresAuth: true, requiresWorkspace: true, permission: 'requests.create' },
  },
  {
    path: '/requests/:id',
    name: 'requests-detail',
    component: () => import('./views/RequestDetailView.vue'),
    meta: detail,
  },
  {
    path: '/requests/:id/edit',
    name: 'requests-edit',
    component: () => import('./views/RequestFormView.vue'),
    meta: { requiresAuth: true, requiresWorkspace: true, permission: 'requests.update_own' },
  },
]

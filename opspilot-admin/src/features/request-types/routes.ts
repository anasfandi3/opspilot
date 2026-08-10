import type { RouteRecordRaw } from 'vue-router'

const viewMeta = {
  requiresAuth: true,
  requiresWorkspace: true,
  permission: 'request_types.view',
} as const
const manageMeta = {
  requiresAuth: true,
  requiresWorkspace: true,
  permission: 'request_types.manage',
} as const
export const requestTypeRoutes: RouteRecordRaw[] = [
  {
    path: '/request-types',
    name: 'request-types',
    component: () => import('./views/RequestTypeListView.vue'),
    meta: viewMeta,
  },
  {
    path: '/request-types/create',
    name: 'request-types-create',
    component: () => import('./views/RequestTypeFormView.vue'),
    meta: manageMeta,
  },
  {
    path: '/request-types/:id',
    name: 'request-types-detail',
    component: () => import('./views/RequestTypeDetailView.vue'),
    meta: viewMeta,
  },
  {
    path: '/request-types/:id/edit',
    name: 'request-types-edit',
    component: () => import('./views/RequestTypeFormView.vue'),
    meta: manageMeta,
  },
]

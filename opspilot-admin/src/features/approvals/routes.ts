import type { RouteRecordRaw } from 'vue-router'
const view = {
  requiresAuth: true,
  requiresWorkspace: true,
  permission: 'approvals.view_assigned',
} as const
export const approvalRoutes: RouteRecordRaw[] = [
  {
    path: '/approvals',
    name: 'approvals',
    component: () => import('./views/ApprovalInboxView.vue'),
    meta: view,
  },
  {
    path: '/approvals/:id',
    name: 'approvals-detail',
    component: () => import('./views/ApprovalDetailView.vue'),
    meta: view,
  },
]

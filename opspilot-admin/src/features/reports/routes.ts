import type { RouteRecordRaw } from 'vue-router'
const meta = { requiresAuth: true, requiresWorkspace: true, permission: 'reports.view' }
export const reportRoutes: RouteRecordRaw[] = [
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('./views/DashboardView.vue'),
    meta,
  },
  {
    path: '/reports/requests',
    name: 'request-report',
    component: () => import('./views/RequestReportView.vue'),
    meta,
  },
  {
    path: '/reports/approvals',
    name: 'approval-report',
    component: () => import('./views/ApprovalReportView.vue'),
    meta,
  },
]

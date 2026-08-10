import type { RouteRecordRaw } from 'vue-router'
export const memberRoutes: RouteRecordRaw[] = [
  {
    path: '/settings/members',
    name: 'members',
    component: () => import('./views/MembersView.vue'),
    meta: { requiresAuth: true, requiresWorkspace: true, permission: 'members.view' },
  },
  {
    path: '/settings/invitations',
    name: 'invitations',
    component: () => import('./views/InvitationsView.vue'),
    meta: { requiresAuth: true, requiresWorkspace: true, permission: 'invitations.view' },
  },
]

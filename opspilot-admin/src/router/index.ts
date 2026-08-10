import { createRouter, createWebHistory } from 'vue-router'
import { authRoutes } from '@/features/auth/routes'
import { workspaceRoutes } from '@/features/workspaces/routes'
import { memberRoutes } from '@/features/members/routes'
import { requestTypeRoutes } from '@/features/request-types/routes'
import { workflowRoutes } from '@/features/workflows/routes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/home' },
    ...authRoutes,
    ...workspaceRoutes,
    ...memberRoutes,
    ...requestTypeRoutes,
    ...workflowRoutes,
    {
      path: '/home',
      name: 'home',
      component: () => import('@/views/HomeView.vue'),
      meta: { requiresAuth: true, requiresWorkspace: true },
    },
    {
      path: '/403',
      name: 'forbidden',
      component: () => import('@/views/ErrorView.vue'),
      meta: { requiresAuth: true },
    },
    { path: '/404', name: 'not-found', component: () => import('@/views/ErrorView.vue') },
    { path: '/ui', name: 'ui', component: () => import('@/views/UiPlaygroundView.vue') },
    { path: '/:pathMatch(.*)*', redirect: '/404' },
  ],
})

export default router

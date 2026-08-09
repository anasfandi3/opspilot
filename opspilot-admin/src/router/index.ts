import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/ui' },
    { path: '/ui', name: 'ui', component: () => import('@/views/UiPlaygroundView.vue') },
  ],
})

export default router

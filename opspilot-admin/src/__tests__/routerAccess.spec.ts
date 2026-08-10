import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { installGuards } from '@/router/guards'
import { safeRedirect, resolveAccessibleHome } from '@/router/home'
import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'
import { workspaceApi } from '@/features/workspaces/api/workspaces'
import type { Workspace } from '@/features/workspaces/types/workspace'
import { routeDocumentTitle } from '@/router/titles'

vi.mock('@/features/workspaces/api/workspaces', () => ({
  workspaceApi: {
    list: vi.fn<typeof workspaceApi.list>(),
    switchTo: vi.fn<typeof workspaceApi.switchTo>(),
  },
}))

function workspace(permissions: string[]): Workspace {
  return {
    id: 1,
    name: 'Workspace',
    slug: 'workspace',
    owner_id: 1,
    role: 'owner',
    permissions,
    created_at: '',
    updated_at: '',
  }
}

function guardedRouter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: {}, meta: { guestOnly: true } },
      { path: '/home', name: 'home', component: {}, meta: { requiresAuth: true } },
      { path: '/403', name: 'forbidden', component: {}, meta: { requiresAuth: true } },
      {
        path: '/reports',
        name: 'reports',
        component: {},
        meta: { requiresAuth: true, permission: 'reports.view' },
      },
    ],
  })
  const pinia = createPinia()
  installGuards(router, pinia)
  return { router, pinia }
}
describe('router access helpers', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.clearAllMocks()
  })
  it('accepts safe local redirects only', () => {
    expect(safeRedirect('/home?tab=1')).toBe('/home?tab=1')
    expect(safeRedirect('//evil.test')).toBeNull()
    expect(safeRedirect('https://evil.test')).toBeNull()
  })
  it('uses concise product document titles for known and unknown routes', () => {
    expect(routeDocumentTitle('requests-detail')).toBe('Request details · OpsPilot')
    expect(routeDocumentTitle('not-found')).toBe('Page not found · OpsPilot')
    expect(routeDocumentTitle(undefined)).toBe('OpsPilot')
  })
  it('selects the first registered accessible capability', () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/home', name: 'home', component: {} },
        {
          path: '/reports',
          name: 'dashboard',
          component: {},
          meta: { permission: 'reports.view' },
        },
      ],
    })
    expect(resolveAccessibleHome(router, () => false)).toBe('/home')
    expect(resolveAccessibleHome(router, () => true)).toBe('/reports')
  })
  it('uses anyPermissions when resolving the first genuinely accessible home', () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/home', name: 'home', component: {} },
        {
          path: '/dashboard',
          name: 'dashboard',
          component: {},
          meta: { permission: 'dashboard.view' },
        },
        {
          path: '/requests',
          name: 'requests',
          component: {},
          meta: { anyPermissions: ['requests.view', 'requests.manage'] },
        },
        { path: '/approvals', name: 'approvals', component: {} },
      ],
    })
    expect(resolveAccessibleHome(router, (permission) => permission === 'requests.manage')).toBe(
      '/requests',
    )
    expect(resolveAccessibleHome(router, () => false)).toBe('/approvals')
  })
  it('lands on dashboard first and retains capability fallbacks', () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/dashboard',
          name: 'dashboard',
          component: {},
          meta: { permission: 'reports.view' },
        },
        {
          path: '/requests',
          name: 'requests',
          component: {},
          meta: { permission: 'requests.view_own' },
        },
        {
          path: '/approvals',
          name: 'approvals',
          component: {},
          meta: { permission: 'approvals.view_assigned' },
        },
        { path: '/home', name: 'home', component: {} },
      ],
    })
    expect(resolveAccessibleHome(router, (permission) => permission === 'reports.view')).toBe(
      '/dashboard',
    )
    expect(resolveAccessibleHome(router, (permission) => permission === 'requests.view_own')).toBe(
      '/requests',
    )
    expect(
      resolveAccessibleHome(router, (permission) => permission === 'approvals.view_assigned'),
    ).toBe('/approvals')
    expect(resolveAccessibleHome(router, () => false)).toBe('/home')
    expect(safeRedirect('/reports/requests?from=2026-01-01')).toBe(
      '/reports/requests?from=2026-01-01',
    )
  })
  it('preserves the full destination when redirecting a guest', async () => {
    const { router, pinia } = guardedRouter()
    useAuthStore(pinia).$patch({ initialized: true, user: null })
    await router.push('/reports?range=week')
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/reports?range=week')
  })
  it('redirects authenticated users without a route permission to 403', async () => {
    const { router, pinia } = guardedRouter()
    useAuthStore(pinia).$patch({
      initialized: true,
      user: { id: 1, name: 'Owner', email: 'owner@example.test', created_at: '', updated_at: '' },
    })
    useWorkspaceStore(pinia).$patch({
      initialized: true,
      currentWorkspace: {
        id: 1,
        name: 'Workspace',
        slug: 'workspace',
        owner_id: 1,
        role: 'requester',
        permissions: [],
        created_at: '',
        updated_at: '',
      },
    })
    await router.push('/reports')
    expect(router.currentRoute.value.name).toBe('forbidden')
  })
  it('initializes workspace context before allowing a permission-gated route', async () => {
    const allowedWorkspace = workspace(['reports.view'])
    vi.mocked(workspaceApi.list).mockResolvedValue([allowedWorkspace])
    vi.mocked(workspaceApi.switchTo).mockResolvedValue(allowedWorkspace)
    const { router, pinia } = guardedRouter()
    useAuthStore(pinia).$patch({
      initialized: true,
      user: { id: 1, name: 'Owner', email: 'owner@example.test', created_at: '', updated_at: '' },
    })

    await router.push('/reports')

    expect(workspaceApi.list).toHaveBeenCalledOnce()
    expect(useWorkspaceStore(pinia).initialized).toBe(true)
    expect(router.currentRoute.value.name).toBe('reports')
  })
  it('initializes workspace context before denying a missing route permission', async () => {
    const deniedWorkspace = workspace([])
    vi.mocked(workspaceApi.list).mockResolvedValue([deniedWorkspace])
    vi.mocked(workspaceApi.switchTo).mockResolvedValue(deniedWorkspace)
    const { router, pinia } = guardedRouter()
    useAuthStore(pinia).$patch({
      initialized: true,
      user: { id: 1, name: 'Owner', email: 'owner@example.test', created_at: '', updated_at: '' },
    })

    await router.push('/reports')

    expect(workspaceApi.list).toHaveBeenCalledOnce()
    expect(router.currentRoute.value.name).toBe('forbidden')
  })
})

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { apiClient } from '@/lib/api/client'
import { configureSessionExpiryHandler } from '@/lib/api/sessionExpiry'
import { installSessionExpiryHandler } from '@/features/auth/sessionExpiry'
import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'
import { queryClient } from '@/lib/queryClient'

const user = { id: 1, name: 'Owner', email: 'owner@example.test', created_at: '', updated_at: '' }
const workspace = {
  id: 1,
  name: 'Workspace',
  slug: 'workspace',
  owner_id: 1,
  role: 'owner',
  permissions: ['reports.view'],
  created_at: '',
  updated_at: '',
}

function unauthorizedResponse() {
  return new Response(JSON.stringify({ message: 'Unauthenticated.' }), {
    status: 401,
    headers: { 'Content-Type': 'application/json' },
  })
}

function testRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: {} },
      { path: '/protected', name: 'protected', component: {} },
    ],
  })
}

describe('session expiry handling', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    queryClient.clear()
    configureSessionExpiryHandler(null)
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  it('does not globally handle the expected anonymous bootstrap 401', async () => {
    const handler = vi.fn<() => void>()
    configureSessionExpiryHandler(handler)
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(unauthorizedResponse()))

    const auth = useAuthStore()
    await auth.bootstrap()

    expect(handler).not.toHaveBeenCalled()
    expect(auth.authenticated).toBe(false)
    expect(auth.initialized).toBe(true)
  })

  it('resets authenticated context and preserves a safe local redirect once during a 401 storm', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const router = testRouter()
    await router.push('/protected?tab=activity')
    const auth = useAuthStore(pinia)
    const workspaces = useWorkspaceStore(pinia)
    auth.$patch({ user, initialized: true })
    workspaces.$patch({
      workspaces: [workspace],
      currentWorkspace: workspace,
      initialized: true,
    })
    localStorage.setItem('opspilot-current-workspace', '1')
    queryClient.setQueryData(['global', 'profile'], user)
    const expire = vi.spyOn(auth, 'expireSession')
    installSessionExpiryHandler(router, pinia)
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(unauthorizedResponse()))

    await Promise.allSettled([apiClient.get('/api/v1/one'), apiClient.get('/api/v1/two')])

    expect(expire).toHaveBeenCalledOnce()
    expect(auth.user).toBeNull()
    expect(workspaces.currentWorkspace).toBeNull()
    expect(queryClient.getQueryCache().getAll()).toHaveLength(0)
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/protected?tab=activity')
  })
})

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { authApi } from '@/features/auth/api/auth'
import { workspaceApi } from '@/features/workspaces/api/workspaces'
import { ApiError } from '@/lib/api/errors'
import { useAuthStore } from '@/stores/auth'
vi.mock('@/features/auth/api/auth', () => ({
  authApi: {
    currentUser: vi.fn<typeof authApi.currentUser>(),
    login: vi.fn<typeof authApi.login>(),
    logout: vi.fn<typeof authApi.logout>(),
  },
}))
vi.mock('@/features/workspaces/api/workspaces', () => ({
  workspaceApi: {
    list: vi.fn<typeof workspaceApi.list>(),
    switchTo: vi.fn<typeof workspaceApi.switchTo>(),
  },
}))
const user = { id: 1, name: 'Owner', email: 'owner@example.com', created_at: '', updated_at: '' }
describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
    vi.mocked(workspaceApi.list).mockResolvedValue([])
  })
  it('bootstraps an authenticated user only once', async () => {
    vi.mocked(authApi.currentUser).mockResolvedValue(user)
    const store = useAuthStore()
    await Promise.all([store.bootstrap(), store.bootstrap()])
    expect(store.user).toEqual(user)
    expect(authApi.currentUser).toHaveBeenCalledOnce()
  })
  it('settles unauthenticated bootstrap', async () => {
    vi.mocked(authApi.currentUser).mockRejectedValue(new ApiError('unauthenticated', 'No', 401))
    const store = useAuthStore()
    await store.bootstrap()
    expect(store.authenticated).toBe(false)
    expect(store.initialized).toBe(true)
  })
  it('logs in and resets durable context on logout', async () => {
    vi.mocked(authApi.login).mockResolvedValue(user)
    vi.mocked(authApi.logout).mockResolvedValue()
    const store = useAuthStore()
    await store.login({ email: 'x', password: 'y' })
    expect(store.authenticated).toBe(true)
    await store.logout()
    expect(store.user).toBeNull()
    expect(localStorage.getItem('opspilot-current-workspace')).toBeNull()
  })
})

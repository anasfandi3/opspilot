import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { workspaceApi } from '@/features/workspaces/api/workspaces'
import { useWorkspaceStore, WORKSPACE_STORAGE_KEY } from '@/stores/workspace'
import type { Workspace } from '@/features/workspaces/types/workspace'
import { queryClient } from '@/lib/queryClient'
import { workspaceQueryKey } from '@/lib/queryKeys'
vi.mock('@/features/workspaces/api/workspaces', () => ({
  workspaceApi: {
    list: vi.fn<typeof workspaceApi.list>(),
    switchTo: vi.fn<typeof workspaceApi.switchTo>(),
  },
}))
const make = (id: number): Workspace => ({
  id,
  name: `Workspace ${id}`,
  slug: `w-${id}`,
  owner_id: 1,
  role: 'owner',
  permissions: ['workspace.view'],
  created_at: '',
  updated_at: '',
})
describe('workspace store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    queryClient.clear()
    vi.clearAllMocks()
    vi.mocked(workspaceApi.switchTo).mockImplementation(async (id) => make(id))
  })
  it('auto-selects one workspace', async () => {
    vi.mocked(workspaceApi.list).mockResolvedValue([make(1)])
    const store = useWorkspaceStore()
    await store.initialize()
    expect(store.currentWorkspaceId).toBe(1)
  })
  it('restores a valid persisted workspace', async () => {
    localStorage.setItem(WORKSPACE_STORAGE_KEY, '2')
    vi.mocked(workspaceApi.list).mockResolvedValue([make(1), make(2)])
    const store = useWorkspaceStore()
    await store.initialize()
    expect(store.currentWorkspaceId).toBe(2)
  })
  it('falls back from an invalid persisted workspace', async () => {
    localStorage.setItem(WORKSPACE_STORAGE_KEY, '99')
    vi.mocked(workspaceApi.list).mockResolvedValue([make(1), make(2)])
    const store = useWorkspaceStore()
    await store.initialize()
    expect(store.currentWorkspaceId).toBe(1)
  })
  it('resets workspace context', () => {
    const store = useWorkspaceStore()
    store.$patch({ workspaces: [make(1)], currentWorkspace: make(1), initialized: true })
    store.reset()
    expect(store.workspaces).toEqual([])
    expect(store.currentWorkspace).toBeNull()
  })
  it('removes only explicitly scoped queries for the previous workspace', async () => {
    const store = useWorkspaceStore()
    store.$patch({ workspaces: [make(1), make(2)], currentWorkspace: make(1), initialized: true })
    const previousKey = workspaceQueryKey(1, 'requests')
    const nextKey = workspaceQueryKey(2, 'requests')
    const unrelatedKey = ['request', 1, 'detail'] as const
    queryClient.setQueryData(previousKey, 'previous')
    queryClient.setQueryData(nextKey, 'next')
    queryClient.setQueryData(unrelatedKey, 'unrelated')

    await store.selectWorkspace(2)

    expect(queryClient.getQueryData(previousKey)).toBeUndefined()
    expect(queryClient.getQueryData(nextKey)).toBe('next')
    expect(queryClient.getQueryData(unrelatedKey)).toBe('unrelated')
  })
  it('allows only one workspace switch request at a time', async () => {
    let finishSwitch: (workspace: Workspace) => void = () => undefined
    vi.mocked(workspaceApi.switchTo).mockImplementation(
      () => new Promise((resolve) => (finishSwitch = resolve)),
    )
    const store = useWorkspaceStore()
    store.$patch({
      workspaces: [make(1), make(2), make(3)],
      currentWorkspace: make(1),
      initialized: true,
    })

    const first = store.selectWorkspace(2)
    const second = store.selectWorkspace(3)

    expect(workspaceApi.switchTo).toHaveBeenCalledExactlyOnceWith(2)
    finishSwitch(make(2))
    await Promise.all([first, second])
    expect(store.currentWorkspaceId).toBe(2)
    expect(store.switching).toBe(false)
  })
  it('keeps the previous selection and persistence when switching fails', async () => {
    vi.mocked(workspaceApi.switchTo).mockRejectedValue(new Error('Switch failed'))
    const store = useWorkspaceStore()
    store.$patch({ workspaces: [make(1), make(2)], currentWorkspace: make(1), initialized: true })
    localStorage.setItem(WORKSPACE_STORAGE_KEY, '1')

    await expect(store.selectWorkspace(2)).rejects.toThrow('Switch failed')

    expect(store.currentWorkspaceId).toBe(1)
    expect(localStorage.getItem(WORKSPACE_STORAGE_KEY)).toBe('1')
    expect(store.switching).toBe(false)
  })
  it('updates and persists a successful switch', async () => {
    const store = useWorkspaceStore()
    store.$patch({ workspaces: [make(1), make(2)], currentWorkspace: make(1), initialized: true })
    localStorage.setItem(WORKSPACE_STORAGE_KEY, '1')

    await store.selectWorkspace(2)

    expect(store.currentWorkspaceId).toBe(2)
    expect(localStorage.getItem(WORKSPACE_STORAGE_KEY)).toBe('2')
    expect(store.switching).toBe(false)
  })
})

import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthorization } from '@/composables/useAuthorization'
import { useWorkspaceStore } from '@/stores/workspace'
describe('workspace authorization', () => {
  beforeEach(() => setActivePinia(createPinia()))
  it('evaluates can, canAny, and canAll per current workspace', () => {
    const store = useWorkspaceStore()
    store.$patch({
      currentWorkspace: {
        id: 1,
        name: 'A',
        slug: 'a',
        owner_id: 1,
        role: 'admin',
        permissions: ['members.view', 'members.manage'],
        created_at: '',
        updated_at: '',
      },
    })
    const auth = useAuthorization()
    expect(auth.can('members.view')).toBe(true)
    expect(auth.canAny(['reports.view', 'members.manage'])).toBe(true)
    expect(auth.canAll(['members.view', 'members.manage'])).toBe(true)
    store.currentWorkspace = { ...store.currentWorkspace!, id: 2, permissions: ['requests.create'] }
    expect(auth.can('members.view')).toBe(false)
  })
})

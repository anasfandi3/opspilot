import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authApi } from '@/features/auth/api/auth'
import type { AuthUser, LoginCredentials } from '@/features/auth/types/auth'
import { ApiError } from '@/lib/api/errors'
import { queryClient } from '@/lib/queryClient'
import { useWorkspaceStore } from './workspace'

let bootstrapPromise: Promise<void> | null = null
export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const initialized = ref(false)
  const authenticated = computed(() => user.value !== null)
  async function bootstrap() {
    if (initialized.value) return
    if (bootstrapPromise) return bootstrapPromise
    bootstrapPromise = (async () => {
      try {
        user.value = await authApi.currentUser()
      } catch (error) {
        if (!(error instanceof ApiError) || error.kind !== 'unauthenticated') throw error
        user.value = null
      } finally {
        initialized.value = true
        bootstrapPromise = null
      }
    })()
    return bootstrapPromise
  }
  async function login(credentials: LoginCredentials) {
    user.value = await authApi.login(credentials)
    initialized.value = true
    await useWorkspaceStore().initialize()
  }
  async function logout() {
    try {
      await authApi.logout()
    } finally {
      user.value = null
      initialized.value = true
      useWorkspaceStore().reset()
      queryClient.clear()
    }
  }
  function expireSession() {
    user.value = null
    initialized.value = true
    useWorkspaceStore().reset()
    queryClient.clear()
  }
  function resetForTests() {
    user.value = null
    initialized.value = false
    bootstrapPromise = null
  }
  return {
    user,
    initialized,
    authenticated,
    bootstrap,
    login,
    logout,
    expireSession,
    resetForTests,
  }
})

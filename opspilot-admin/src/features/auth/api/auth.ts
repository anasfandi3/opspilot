import { apiClient, type ApiEnvelope } from '@/lib/api/client'
import type { AuthUser, LoginCredentials } from '../types/auth'
export const authApi = {
  currentUser: async () =>
    (
      await apiClient.get<ApiEnvelope<AuthUser>>('/api/v1/me', {
        handleUnauthorized: false,
      })
    ).data,
  login: async (credentials: LoginCredentials) =>
    (
      await apiClient.post<ApiEnvelope<AuthUser>>('/api/v1/auth/session', credentials, {
        csrf: true,
        handleUnauthorized: false,
      })
    ).data,
  logout: async () => {
    await apiClient.post('/api/v1/auth/session/logout', undefined, {
      csrf: true,
      handleUnauthorized: false,
    })
    apiClient.resetCsrf()
  },
}

import { normalizeApiError } from './errors'
import type { ApiEnvelope, LaravelErrorPayload } from './types'
import { handleSessionExpiry } from './sessionExpiry'

const baseUrl = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '')
let csrfReady = false

function xsrfToken(): string | undefined {
  if (typeof document === 'undefined') return undefined
  const cookie = document.cookie
    .split('; ')
    .find((entry) => entry.startsWith('XSRF-TOKEN='))
    ?.slice('XSRF-TOKEN='.length)
  return cookie ? decodeURIComponent(cookie) : undefined
}

async function parsePayload(response: Response): Promise<unknown> {
  if (response.status === 204) return undefined
  const type = response.headers.get('content-type') ?? ''
  return type.includes('json') ? response.json() : response.text()
}

interface RequestOptions extends RequestInit {
  handleUnauthorized?: boolean
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  try {
    const { handleUnauthorized = true, ...init } = options
    const token = init.method !== 'GET' ? xsrfToken() : undefined
    const response = await fetch(`${baseUrl}${path}`, {
      ...init,
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        ...(init.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        ...init.headers,
      },
    })
    const payload = await parsePayload(response)
    if (!response.ok) {
      const error = normalizeApiError(undefined, response.status, payload as LaravelErrorPayload)
      if (error.kind === 'unauthenticated' && handleUnauthorized) await handleSessionExpiry()
      throw error
    }
    return payload as T
  } catch (error) {
    throw normalizeApiError(error)
  }
}

async function ensureCsrf(): Promise<void> {
  if (csrfReady) return
  await request('/sanctum/csrf-cookie')
  csrfReady = true
}
export const apiClient = {
  get: <T>(path: string, options?: RequestOptions) =>
    request<T>(path, { ...options, method: 'GET' }),
  post: async <T>(
    path: string,
    body?: unknown,
    options: { csrf?: boolean; handleUnauthorized?: boolean } = {},
  ) => {
    if (options.csrf) await ensureCsrf()
    return request<T>(path, {
      method: 'POST',
      body: body === undefined ? undefined : JSON.stringify(body),
      handleUnauthorized: options.handleUnauthorized,
    })
  },
  download: async (path: string) => {
    const response = await fetch(`${baseUrl}${path}`, {
      credentials: 'include',
      headers: { Accept: 'application/octet-stream' },
    })
    if (!response.ok) {
      const error = normalizeApiError(
        undefined,
        response.status,
        (await parsePayload(response)) as LaravelErrorPayload,
      )
      if (error.kind === 'unauthenticated') await handleSessionExpiry()
      throw error
    }
    return response.blob()
  },
  resetCsrf: () => {
    csrfReady = false
  },
}
export type { ApiEnvelope }

import type { ApiErrorKind, LaravelErrorPayload } from './types'

export class ApiError extends Error {
  constructor(
    public kind: ApiErrorKind,
    message: string,
    public status?: number,
    public fieldErrors: Record<string, string[]> = {},
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export function normalizeApiError(
  error: unknown,
  status?: number,
  payload?: LaravelErrorPayload,
): ApiError {
  if (error instanceof ApiError) return error
  if (error instanceof TypeError && status === undefined)
    return new ApiError('network', 'Unable to reach the server. Please try again.')
  const message = payload?.message || 'Something went wrong.'
  if (status === 401) return new ApiError('unauthenticated', message, status)
  if (status === 403) return new ApiError('forbidden', message, status)
  if (status === 404) return new ApiError('not-found', message, status)
  if (status === 409) return new ApiError('conflict', message, status)
  if (status === 422) return new ApiError('validation', message, status, payload?.errors)
  if (status && status >= 500) return new ApiError('server', message, status)
  return new ApiError('unknown', error instanceof Error ? error.message : message, status)
}

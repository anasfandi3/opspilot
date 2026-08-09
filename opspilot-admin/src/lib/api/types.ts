export interface ApiEnvelope<T> {
  data: T
  message?: string
}
export interface LaravelErrorPayload {
  message?: string
  errors?: Record<string, string[]>
}
export type ApiErrorKind =
  | 'unauthenticated'
  | 'forbidden'
  | 'not-found'
  | 'validation'
  | 'conflict'
  | 'server'
  | 'network'
  | 'unknown'

import { describe, expect, it } from 'vitest'
import { normalizeApiError } from '@/lib/api/errors'
describe('API error normalization', () => {
  it.each([
    [401, 'unauthenticated'],
    [403, 'forbidden'],
    [404, 'not-found'],
    [409, 'conflict'],
    [500, 'server'],
  ] as const)('maps %s to %s', (status, kind) =>
    expect(normalizeApiError(undefined, status, { message: 'Failure' }).kind).toBe(kind),
  )
  it('preserves Laravel validation field keys', () => {
    const error = normalizeApiError(undefined, 422, {
      message: 'Invalid',
      errors: { 'fields.2.label': ['Required'] },
    })
    expect(error.kind).toBe('validation')
    expect(error.fieldErrors['fields.2.label']).toEqual(['Required'])
  })
  it('separates network and unknown errors', () => {
    expect(normalizeApiError(new TypeError('fetch failed')).kind).toBe('network')
    expect(normalizeApiError(new Error('odd')).kind).toBe('unknown')
  })
})

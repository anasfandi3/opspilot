import { describe, expect, it } from 'vitest'
import type { LocationQuery } from 'vue-router'
import { parseTableQuery, serializeTableQuery } from '@/composables/useTableQuery'
const options = { filterKeys: ['status'], allowedPageSizes: [10, 20], allowedSorts: ['created_at'] }

describe('table query state', () => {
  it('parses and serializes valid server query state', () => {
    const parsed = parseTableQuery(
      {
        search: 'purchase',
        status: 'pending',
        sort: 'created_at',
        direction: 'desc',
        page: '2',
        per_page: '20',
      } as LocationQuery,
      options,
    )
    expect(parsed).toEqual({
      search: 'purchase',
      filters: { status: 'pending' },
      sort: 'created_at',
      direction: 'desc',
      page: 2,
      perPage: 20,
    })
    expect(serializeTableQuery(parsed)).toMatchObject({
      page: '2',
      per_page: '20',
      status: 'pending',
    })
  })
  it('falls back safely for invalid values', () => {
    expect(
      parseTableQuery(
        { page: '-2', per_page: '999', sort: 'unknown', direction: 'sideways' } as LocationQuery,
        options,
      ),
    ).toEqual({
      search: '',
      filters: { status: '' },
      sort: undefined,
      direction: undefined,
      page: 1,
      perPage: 10,
    })
  })
})

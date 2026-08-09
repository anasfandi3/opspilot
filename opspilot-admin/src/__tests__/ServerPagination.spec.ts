import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ServerPagination from '@/components/app/table/ServerPagination.vue'
import { createPaginationRange } from '@/components/app/table/paginationRange'

describe('server pagination', () => {
  it('shows every page for a small page count', () => {
    expect(createPaginationRange(2, 5)).toEqual([1, 2, 3, 4, 5])
  })

  it('condenses a middle page with ellipses', () => {
    expect(createPaginationRange(12, 24)).toEqual([
      1,
      'ellipsis-start',
      10,
      11,
      12,
      13,
      14,
      'ellipsis-end',
      24,
    ])
  })

  it('handles first and last boundaries cleanly', () => {
    expect(createPaginationRange(1, 24)).toEqual([1, 2, 3, 4, 5, 'ellipsis-end', 24])
    expect(createPaginationRange(24, 24)).toEqual([1, 'ellipsis-start', 20, 21, 22, 23, 24])
  })

  it('marks the current page and emits a selected page', async () => {
    const wrapper = mount(ServerPagination, { props: { page: 3, pageCount: 8 } })
    expect(wrapper.get('[aria-current="page"]').text()).toBe('3')
    await wrapper.get('[aria-label="Go to page 4"]').trigger('click')
    expect(wrapper.emitted('update:page')).toEqual([[4]])
  })
})

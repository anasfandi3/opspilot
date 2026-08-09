import { describe, expect, it } from 'vitest'
import { formGridColumns } from '@/components/app/forms/FormGrid.vue'

describe('FormGrid class mapping', () => {
  it('keeps mobile single-column and applies the configured desktop columns', () => {
    expect(formGridColumns[2]).toContain('grid-cols-1')
    expect(formGridColumns[2]).toContain('md:grid-cols-2')
    expect(formGridColumns[3]).toContain('xl:grid-cols-3')
  })
})

export type PaginationRangeItem = number | 'ellipsis-start' | 'ellipsis-end'

export function createPaginationRange(page: number, pageCount: number): PaginationRangeItem[] {
  if (pageCount <= 1) return [1]
  if (pageCount <= 7) return Array.from({ length: pageCount }, (_, index) => index + 1)

  const current = Math.min(Math.max(page, 1), pageCount)

  if (current <= 4) return [1, 2, 3, 4, 5, 'ellipsis-end', pageCount]
  if (current >= pageCount - 3)
    return [
      1,
      'ellipsis-start',
      pageCount - 4,
      pageCount - 3,
      pageCount - 2,
      pageCount - 1,
      pageCount,
    ]

  return [
    1,
    'ellipsis-start',
    current - 2,
    current - 1,
    current,
    current + 1,
    current + 2,
    'ellipsis-end',
    pageCount,
  ]
}

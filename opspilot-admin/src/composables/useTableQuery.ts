import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQuery, type LocationQueryRaw } from 'vue-router'

export type SortDirection = 'asc' | 'desc'
export interface TableQueryState {
  search: string
  filters: Record<string, string>
  sort?: string
  direction?: SortDirection
  page: number
  perPage: number
}
export interface TableQueryOptions {
  filterKeys?: readonly string[]
  defaultPerPage?: number
  allowedPageSizes?: readonly number[]
  allowedSorts?: readonly string[]
}

const first = (value: LocationQuery[string] | undefined) =>
  Array.isArray(value) ? value[0] : value
const positiveInt = (value: LocationQuery[string] | undefined, fallback: number) => {
  const parsed = Number(first(value))
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback
}

export function parseTableQuery(
  query: LocationQuery,
  options: TableQueryOptions = {},
): TableQueryState {
  const defaultPerPage = options.defaultPerPage ?? 10
  const candidateSize = positiveInt(query.per_page, defaultPerPage)
  const perPage =
    options.allowedPageSizes?.includes(candidateSize) === false ? defaultPerPage : candidateSize
  const candidateSort = first(query.sort) || undefined
  const sort =
    !candidateSort || options.allowedSorts?.includes(candidateSort) !== false
      ? candidateSort
      : undefined
  const rawDirection = first(query.direction)
  const direction =
    sort && (rawDirection === 'asc' || rawDirection === 'desc') ? rawDirection : undefined
  return {
    search: first(query.search) ?? '',
    filters: Object.fromEntries(
      (options.filterKeys ?? []).map((key) => [key, first(query[key]) ?? '']),
    ),
    sort,
    direction,
    page: positiveInt(query.page, 1),
    perPage,
  }
}

export function serializeTableQuery(state: TableQueryState): LocationQueryRaw {
  const query: LocationQueryRaw = {}
  if (state.search) query.search = state.search
  for (const [key, value] of Object.entries(state.filters)) if (value) query[key] = value
  if (state.sort) {
    query.sort = state.sort
    if (state.direction) query.direction = state.direction
  }
  if (state.page > 1) query.page = String(state.page)
  query.per_page = String(state.perPage)
  return query
}

export function useTableQuery(options: TableQueryOptions = {}) {
  const route = useRoute()
  const router = useRouter()
  const state = ref(parseTableQuery(route.query, options))
  let syncing = false
  watch(
    () => route.query,
    (query) => {
      syncing = true
      state.value = parseTableQuery(query, options)
      syncing = false
    },
  )
  watch(
    state,
    async (value) => {
      if (!syncing) {
        const managedKeys = new Set([
          'search',
          'sort',
          'direction',
          'page',
          'per_page',
          ...(options.filterKeys ?? []),
        ])
        const preserved = Object.fromEntries(
          Object.entries(route.query).filter(([key]) => !managedKeys.has(key)),
        )
        await router.push({ query: { ...preserved, ...serializeTableQuery(value) } })
      }
    },
    { deep: true },
  )
  const activeFilterCount = computed(
    () => Object.values(state.value.filters).filter(Boolean).length,
  )
  function patch(update: Partial<TableQueryState>, resetPage = true) {
    state.value = {
      ...state.value,
      ...update,
      page: resetPage && update.page === undefined ? 1 : (update.page ?? state.value.page),
    }
  }
  return { state, activeFilterCount, patch }
}

<script setup lang="ts">
import { computed, h, ref, watch } from 'vue'
import { useDebounce } from '@vueuse/core'
import type { ColumnDef, RowSelectionState } from '@tanstack/vue-table'
import { Eye, MoreHorizontal, SlidersHorizontal } from '@lucide/vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import DataTable from '@/components/app/table/DataTable.vue'
import { useTableQuery, type SortDirection } from '@/composables/useTableQuery'

interface DemoRow {
  id: string
  title: string
  status: 'Pending' | 'Approved' | 'Draft'
  category: 'Operations' | 'Finance' | 'People'
  owner: string
  created_at: string
}
const owners = ['Maya Chen', 'Noah Williams', 'Ava Patel', 'Omar Hassan']
const rows: DemoRow[] = Array.from({ length: 47 }, (_, index) => ({
  id: String(index + 1),
  title:
    ['Vendor onboarding', 'Purchase review', 'Access renewal', 'Policy exception'][index % 4] +
    ` #${100 + index}`,
  status: (['Pending', 'Approved', 'Draft'] as const)[index % 3]!,
  category: (['Operations', 'Finance', 'People'] as const)[index % 3]!,
  owner: owners[index % owners.length]!,
  created_at: new Date(2026, 7, 9 - index).toISOString().slice(0, 10),
}))
const { state, activeFilterCount, patch } = useTableQuery({
  filterKeys: ['status', 'category', 'owner'],
  defaultPerPage: 10,
  allowedPageSizes: [5, 10, 20],
  allowedSorts: ['title', 'status', 'category', 'owner', 'created_at'],
})
const searchInput = ref(state.value.search)
const debouncedSearch = useDebounce(searchInput, 350)
const selected = ref<RowSelectionState>({})
const filtersOpen = ref(false)
const advancedOwner = ref(state.value.filters.owner ?? '')
watch(debouncedSearch, (value) => patch({ search: value }))
watch(
  () => state.value.search,
  (value) => (searchInput.value = value),
)
const filtered = computed(() =>
  rows
    .filter(
      (row) =>
        (!state.value.search ||
          row.title.toLowerCase().includes(state.value.search.toLowerCase()) ||
          row.owner.toLowerCase().includes(state.value.search.toLowerCase())) &&
        (!state.value.filters.status || row.status.toLowerCase() === state.value.filters.status) &&
        (!state.value.filters.category ||
          row.category.toLowerCase() === state.value.filters.category) &&
        (!state.value.filters.owner || row.owner === state.value.filters.owner),
    )
    .sort((a, b) => {
      if (!state.value.sort) return 0
      const av = a[state.value.sort as keyof DemoRow]
      const bv = b[state.value.sort as keyof DemoRow]
      return String(av).localeCompare(String(bv)) * (state.value.direction === 'desc' ? -1 : 1)
    }),
)
const paged = computed(() =>
  filtered.value.slice(
    (state.value.page - 1) * state.value.perPage,
    state.value.page * state.value.perPage,
  ),
)
const columns: ColumnDef<DemoRow>[] = [
  {
    accessorKey: 'title',
    header: 'Request',
    cell: ({ row }) =>
      h('div', [
        h('p', { class: 'font-medium' }, row.original.title),
        h('p', { class: 'text-xs text-muted-foreground' }, `ID ${row.original.id}`),
      ]),
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) =>
      h(
        Badge,
        { variant: row.original.status === 'Approved' ? 'default' : 'secondary' },
        () => row.original.status,
      ),
  },
  { accessorKey: 'category', header: 'Category' },
  { accessorKey: 'owner', header: 'Owner' },
  { accessorKey: 'created_at', header: 'Created' },
  {
    id: 'actions',
    header: 'Actions',
    enableSorting: false,
    cell: ({ row }) =>
      h('div', { class: 'flex justify-end gap-1' }, [
        h(
          Button,
          { variant: 'ghost', size: 'sm', 'aria-label': `View ${row.original.title}` },
          () => [h(Eye), 'View'],
        ),
        h(DropdownMenu, null, {
          default: () => [
            h(DropdownMenuTrigger, { asChild: true }, () =>
              h(
                Button,
                {
                  variant: 'ghost',
                  size: 'icon-sm',
                  'aria-label': `More actions for ${row.original.title}`,
                },
                () => h(MoreHorizontal),
              ),
            ),
            h(DropdownMenuContent, { align: 'end' }, () => [
              h(DropdownMenuItem, null, () => 'Duplicate'),
              h(DropdownMenuItem, null, () => 'Archive'),
            ]),
          ],
        }),
      ]),
  },
]
function sort(column?: string, direction?: SortDirection) {
  patch({ sort: column, direction })
}
function reset() {
  advancedOwner.value = ''
  patch({ filters: { status: '', category: '', owner: '' } })
  filtersOpen.value = false
}
function applyAdvanced() {
  patch({ filters: { ...state.value.filters, owner: advancedOwner.value } })
  filtersOpen.value = false
}
</script>
<template>
  <section id="table" class="space-y-5">
    <div>
      <h2 class="text-xl font-semibold">Server-driven data table</h2>
      <p class="text-sm text-muted-foreground">
        Sorting, search, filters, pagination, and page size synchronize to the URL. The demo applies
        them to local data only.
      </p>
    </div>
    <div class="space-y-4 rounded-xl border bg-card p-4 md:p-5">
      <div class="flex flex-col gap-3 xl:flex-row">
        <Input
          v-model="searchInput"
          class="xl:max-w-sm"
          aria-label="Search requests"
          placeholder="Search requests or owners…"
        />
        <div class="flex flex-1 flex-wrap gap-2">
          <Select
            :model-value="state.filters.status || 'all'"
            @update:model-value="
              (value) =>
                patch({
                  filters: { ...state.filters, status: value === 'all' ? '' : String(value) },
                })
            "
            ><SelectTrigger class="w-40"><SelectValue placeholder="Status" /></SelectTrigger
            ><SelectContent
              ><SelectItem value="all">All statuses</SelectItem
              ><SelectItem value="pending">Pending</SelectItem
              ><SelectItem value="approved">Approved</SelectItem
              ><SelectItem value="draft">Draft</SelectItem></SelectContent
            ></Select
          ><Select
            :model-value="state.filters.category || 'all'"
            @update:model-value="
              (value) =>
                patch({
                  filters: { ...state.filters, category: value === 'all' ? '' : String(value) },
                })
            "
            ><SelectTrigger class="w-44"><SelectValue placeholder="Category" /></SelectTrigger
            ><SelectContent
              ><SelectItem value="all">All categories</SelectItem
              ><SelectItem value="operations">Operations</SelectItem
              ><SelectItem value="finance">Finance</SelectItem
              ><SelectItem value="people">People</SelectItem></SelectContent
            ></Select
          ><Button variant="outline" @click="filtersOpen = true"
            ><SlidersHorizontal />Advanced<span
              v-if="activeFilterCount"
              class="rounded-full bg-primary px-1.5 text-xs text-primary-foreground"
              >{{ activeFilterCount }}</span
            ></Button
          >
        </div>
      </div>
      <DataTable
        v-model:selected="selected"
        :columns="columns"
        :data="paged"
        :total="filtered.length"
        :page="state.page"
        :per-page="state.perPage"
        :sort="state.sort"
        :direction="state.direction"
        :page-sizes="[5, 10, 20]"
        @update:page="(page) => patch({ page }, false)"
        @update:per-page="(perPage) => patch({ perPage })"
        @sort="sort"
        ><template #bulk
          ><Button size="sm" variant="outline">Assign</Button
          ><Button size="sm" variant="outline">Export visible</Button></template
        ></DataTable
      >
    </div>
    <Sheet v-model:open="filtersOpen"
      ><SheetContent
        ><SheetHeader
          ><SheetTitle>Advanced filters</SheetTitle
          ><SheetDescription
            >Apply optional filters without leaving the table.</SheetDescription
          ></SheetHeader
        >
        <div class="space-y-2 p-4">
          <label class="text-sm font-medium">Owner</label
          ><Select v-model="advancedOwner"
            ><SelectTrigger><SelectValue placeholder="Any owner" /></SelectTrigger
            ><SelectContent
              ><SelectItem value="">Any owner</SelectItem
              ><SelectItem v-for="owner in owners" :key="owner" :value="owner">{{
                owner
              }}</SelectItem></SelectContent
            ></Select
          >
        </div>
        <SheetFooter
          ><Button variant="outline" @click="reset">Reset all</Button
          ><Button @click="applyAdvanced">Apply filters</Button></SheetFooter
        ></SheetContent
      ></Sheet
    >
  </section>
</template>

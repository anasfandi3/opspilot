<script setup lang="ts" generic="T extends { id: string | number }">
import { computed, h } from 'vue'
import {
  FlexRender,
  getCoreRowModel,
  useVueTable,
  type ColumnDef,
  type RowSelectionState,
  type Updater,
} from '@tanstack/vue-table'
import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import EmptyState from '@/components/app/feedback/EmptyState.vue'
import type { SortDirection } from '@/composables/useTableQuery'
import ServerPagination from './ServerPagination.vue'

const props = withDefaults(
  defineProps<{
    columns: ColumnDef<T>[]
    data: T[]
    total: number
    page: number
    perPage: number
    sort?: string
    direction?: SortDirection
    loading?: boolean
    pageSizes?: number[]
    selected?: RowSelectionState
    selectable?: boolean
  }>(),
  { pageSizes: () => [5, 10, 20, 50], selected: () => ({}), selectable: true },
)
const emit = defineEmits<{
  'update:page': [value: number]
  'update:perPage': [value: number]
  sort: [column?: string, direction?: SortDirection]
  'update:selected': [value: RowSelectionState]
}>()
const pageCount = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)))
const rangeStart = computed(() => (props.total ? (props.page - 1) * props.perPage + 1 : 0))
const rangeEnd = computed(() => Math.min(props.page * props.perPage, props.total))
const selectionColumn: ColumnDef<T> = {
  id: 'select',
  header: ({ table }) =>
    h(Checkbox, {
      modelValue: table.getIsAllPageRowsSelected(),
      'aria-label': 'Select all visible rows',
      'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
        table.toggleAllPageRowsSelected(Boolean(value)),
    }),
  cell: ({ row }) =>
    h(Checkbox, {
      modelValue: row.getIsSelected(),
      'aria-label': 'Select row',
      'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
        row.toggleSelected(Boolean(value)),
    }),
  enableSorting: false,
}
const allColumns = computed(() =>
  props.selectable ? [selectionColumn, ...props.columns] : props.columns,
)
const table = useVueTable({
  get data() {
    return props.data
  },
  get columns() {
    return allColumns.value
  },
  getCoreRowModel: getCoreRowModel(),
  manualPagination: true,
  manualSorting: true,
  get rowCount() {
    return props.total
  },
  get state() {
    return {
      rowSelection: props.selected,
      sorting: props.sort ? [{ id: props.sort, desc: props.direction === 'desc' }] : [],
    }
  },
  getRowId: (row) => String(row.id),
  enableRowSelection: props.selectable,
  onRowSelectionChange: (updater: Updater<RowSelectionState>) =>
    emit('update:selected', typeof updater === 'function' ? updater(props.selected) : updater),
})
function toggleSort(id: string) {
  if (props.sort !== id) emit('sort', id, 'asc')
  else if (props.direction === 'asc') emit('sort', id, 'desc')
  else emit('sort', undefined, undefined)
}
</script>
<template>
  <div class="space-y-4">
    <div
      v-if="Object.keys(selected).length"
      class="flex flex-wrap items-center gap-3 rounded-md border bg-muted/40 px-4 py-3 text-sm"
    >
      <strong>{{ Object.keys(selected).length }} selected</strong
      ><slot name="bulk" :clear="() => emit('update:selected', {})" /><Button
        variant="ghost"
        size="sm"
        class="ml-auto"
        @click="emit('update:selected', {})"
        >Clear selection</Button
      >
    </div>
    <div class="w-full max-w-full overflow-hidden rounded-lg border bg-card">
      <Table data-testid="table-scroll" class="w-[800px] min-w-[800px]"
        ><TableHeader
          ><TableRow v-for="group in table.getHeaderGroups()" :key="group.id"
            ><TableHead v-for="header in group.headers" :key="header.id"
              ><button
                v-if="header.column.getCanSort()"
                class="inline-flex items-center gap-1 font-medium"
                @click="toggleSort(header.column.id)"
              >
                <FlexRender
                  :render="header.column.columnDef.header"
                  :props="header.getContext()"
                /><ArrowUp
                  v-if="sort === header.column.id && direction === 'asc'"
                  class="size-3.5"
                /><ArrowDown
                  v-else-if="sort === header.column.id && direction === 'desc'"
                  class="size-3.5"
                /><ArrowUpDown v-else class="size-3.5 text-muted-foreground" /></button
              ><FlexRender
                v-else-if="!header.isPlaceholder"
                :render="header.column.columnDef.header"
                :props="header.getContext()" /></TableHead></TableRow
        ></TableHeader>
        <TableBody
          ><template v-if="loading"
            ><TableRow v-for="row in perPage" :key="row"
              ><TableCell v-for="cell in allColumns.length" :key="cell"
                ><Skeleton class="h-5 w-full" /></TableCell></TableRow></template
          ><template v-else-if="table.getRowModel().rows.length"
            ><TableRow
              v-for="row in table.getRowModel().rows"
              :key="row.id"
              :data-state="row.getIsSelected() ? 'selected' : undefined"
              ><TableCell v-for="cell in row.getVisibleCells()" :key="cell.id"
                ><FlexRender
                  :render="cell.column.columnDef.cell"
                  :props="cell.getContext()" /></TableCell></TableRow></template
          ><TableRow v-else
            ><TableCell :colspan="allColumns.length" class="p-6"
              ><EmptyState
                title="No records found"
                description="Try adjusting your search or filters." /></TableCell></TableRow
        ></TableBody>
      </Table>
    </div>
    <div class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
      <p class="text-muted-foreground">Showing {{ rangeStart }}–{{ rangeEnd }} of {{ total }}</p>
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-muted-foreground">Rows per page</span
        ><Select
          :model-value="String(perPage)"
          @update:model-value="(value) => emit('update:perPage', Number(value))"
          ><SelectTrigger class="w-20"><SelectValue /></SelectTrigger
          ><SelectContent
            ><SelectItem v-for="size in pageSizes" :key="size" :value="String(size)">{{
              size
            }}</SelectItem></SelectContent
          ></Select
        ><ServerPagination
          :page="page"
          :page-count="pageCount"
          @update:page="(value) => emit('update:page', value)"
        />
      </div>
    </div>
  </div>
</template>

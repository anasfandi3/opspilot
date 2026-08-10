<script setup lang="ts">
import { computed, h, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import type { ColumnDef } from '@tanstack/vue-table'
import { RouterLink, useRouter } from 'vue-router'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataTable from '@/components/app/table/DataTable.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { useAuthorization } from '@/composables/useAuthorization'
import { useWorkspaceStore } from '@/stores/workspace'
import { requestTypesApi } from '../api/requestTypes'
import { requestTypeKeys } from '../queries/requestTypeKeys'
import type { RequestType } from '../types/requestType'

const workspace = useWorkspaceStore()
const router = useRouter()
const { can } = useAuthorization()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const query = useQuery({
  queryKey: computed(() => requestTypeKeys.list(workspaceId.value)),
  queryFn: () => requestTypesApi.list(workspaceId.value),
  enabled: computed(() => workspaceId.value > 0),
})
watch(workspaceId, () => window.scrollTo({ top: 0 }))
const columns: ColumnDef<RequestType>[] = [
  {
    accessorKey: 'name',
    header: 'Request type',
    cell: ({ row }) =>
      h('div', [
        h('p', { class: 'font-medium' }, row.original.name),
        h('p', { class: 'text-xs text-muted-foreground' }, row.original.slug),
      ]),
  },
  {
    accessorKey: 'is_active',
    header: 'Status',
    cell: ({ row }) =>
      h(Badge, { variant: row.original.is_active ? 'default' : 'secondary' }, () =>
        row.original.is_active ? 'Active' : 'Inactive',
      ),
  },
  { id: 'fields', header: 'Fields', cell: ({ row }) => String(row.original.fields.length) },
  { id: 'creator', header: 'Created by', cell: ({ row }) => row.original.creator.name },
  {
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) =>
      h('div', { class: 'flex gap-2' }, [
        h(
          Button,
          {
            size: 'sm',
            variant: 'outline',
            onClick: () => router.push(`/request-types/${row.original.id}`),
          },
          () => 'View',
        ),
        can('request_types.manage')
          ? h(
              Button,
              {
                size: 'sm',
                variant: 'ghost',
                onClick: () => router.push(`/request-types/${row.original.id}/edit`),
              },
              () => 'Edit',
            )
          : null,
      ]),
  },
]
</script>
<template>
  <AppShell
    ><PageHeader
      title="Request types"
      description="Configure the request schemas available in this workspace."
      ><template v-if="can('request_types.manage')" #actions
        ><Button as-child
          ><RouterLink to="/request-types/create">Create request type</RouterLink></Button
        ></template
      ></PageHeader
    >
    <div v-if="query.isError.value" class="rounded-lg border p-6">
      <p class="text-sm text-destructive">{{ query.error.value?.message }}</p>
      <Button class="mt-4" variant="outline" @click="query.refetch()">Retry</Button>
    </div>
    <DataTable
      v-else
      :columns="columns"
      :data="query.data.value ?? []"
      :total="query.data.value?.length ?? 0"
      :page="1"
      :per-page="Math.max(query.data.value?.length ?? 0, 1)"
      :loading="query.isPending.value"
      :selectable="false"
      :page-sizes="[Math.max(query.data.value?.length ?? 0, 1)]"
    />
  </AppShell>
</template>

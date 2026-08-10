<script setup lang="ts">
import { computed, h } from 'vue'
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
import { requestTypesApi } from '@/features/request-types/api/requestTypes'
import { requestTypeKeys } from '@/features/request-types/queries/requestTypeKeys'
import { workflowsApi } from '../api/workflows'
import { workflowKeys } from '../queries/workflowKeys'
import type { WorkflowListItem } from '../types/workflow'
const workspace = useWorkspaceStore()
const router = useRouter()
const { can } = useAuthorization()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const types = useQuery({
  queryKey: computed(() => requestTypeKeys.list(workspaceId.value)),
  queryFn: () => requestTypesApi.list(workspaceId.value),
  enabled: computed(() => workspaceId.value > 0),
})
const query = useQuery({
  queryKey: computed(() => workflowKeys.list(workspaceId.value)),
  queryFn: () => workflowsApi.all(workspaceId.value, types.data.value ?? []),
  enabled: computed(() => workspaceId.value > 0 && !!types.data.value),
})
function retry() {
  void Promise.all([types.refetch(), query.refetch()])
}
const columns: ColumnDef<WorkflowListItem>[] = [
  {
    accessorKey: 'name',
    header: 'Workflow',
    cell: ({ row }) =>
      h('div', [
        h('p', { class: 'font-medium' }, row.original.name),
        h('p', { class: 'text-xs text-muted-foreground' }, `Version ${row.original.version}`),
      ]),
  },
  { id: 'requestType', header: 'Request type', cell: ({ row }) => row.original.requestType.name },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) =>
      h(
        Badge,
        { variant: row.original.status === 'active' ? 'default' : 'secondary' },
        () => row.original.status,
      ),
  },
  { id: 'steps', header: 'Steps', cell: ({ row }) => String(row.original.steps.length) },
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
            onClick: () => router.push(`/workflows/${row.original.id}`),
          },
          () => 'View',
        ),
        can('workflows.manage') && row.original.status === 'draft'
          ? h(
              Button,
              {
                size: 'sm',
                variant: 'ghost',
                onClick: () => router.push(`/workflows/${row.original.id}/edit`),
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
      title="Workflows"
      description="Configure versioned sequential approval definitions."
      ><template v-if="can('workflows.manage')" #actions
        ><Button as-child
          ><RouterLink to="/workflows/create">Create workflow</RouterLink></Button
        ></template
      ></PageHeader
    >
    <div
      v-if="query.isError.value || types.isError.value"
      class="rounded-lg border p-6 text-sm text-destructive"
    >
      {{ query.error.value?.message || types.error.value?.message }}
      <Button class="ml-3" variant="outline" @click="retry">Retry</Button>
    </div>
    <DataTable
      v-else
      :columns="columns"
      :data="query.data.value ?? []"
      :total="query.data.value?.length ?? 0"
      :page="1"
      :per-page="Math.max(query.data.value?.length ?? 0, 1)"
      :loading="query.isPending.value || types.isPending.value"
      :selectable="false"
      :page-sizes="[Math.max(query.data.value?.length ?? 0, 1)]"
    />
  </AppShell>
</template>

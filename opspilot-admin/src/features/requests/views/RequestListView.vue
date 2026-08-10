<script setup lang="ts">
import { computed, h } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import type { ColumnDef } from '@tanstack/vue-table'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataTable from '@/components/app/table/DataTable.vue'
import { Button } from '@/components/ui/button'
import { useAuthorization } from '@/composables/useAuthorization'
import { useWorkspaceStore } from '@/stores/workspace'
import { useAuthStore } from '@/stores/auth'
import { requestsApi } from '../api/requests'
import { requestKeys, type RequestFilters } from '../queries/requestKeys'
import type { RequestSummary } from '../types/request'
import RequestStatusBadge from '../components/RequestStatusBadge.vue'
import { canEditRequest } from '../requestActions'
const workspace = useWorkspaceStore(),
  auth = useAuthStore(),
  route = useRoute(),
  router = useRouter(),
  { can } = useAuthorization()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const filters = computed<RequestFilters>(() => ({
  page: Math.max(1, Number(route.query.page) || 1),
  perPage: Math.min(100, Math.max(1, Number(route.query.per_page) || 20)),
  status: typeof route.query.status === 'string' ? route.query.status : undefined,
  requestTypeId: Number(route.query.request_type_id) || undefined,
}))
const query = useQuery({
  queryKey: computed(() => requestKeys.list(workspaceId.value, filters.value)),
  queryFn: () => requestsApi.list(workspaceId.value, filters.value),
  enabled: computed(() => workspaceId.value > 0),
})
function update(values: Record<string, string | undefined>) {
  const next = { ...route.query, ...values }
  Object.keys(next).forEach((key) => next[key] === undefined && delete next[key])
  void router.replace({ query: next })
}
const columns: ColumnDef<RequestSummary>[] = [
  { accessorKey: 'id', header: 'Request', cell: ({ row }) => `#${row.original.id}` },
  { id: 'type', header: 'Request type', cell: ({ row }) => row.original.request_type.name },
  { id: 'creator', header: 'Requester', cell: ({ row }) => row.original.creator.name },
  {
    id: 'status',
    header: 'Status',
    cell: ({ row }) => h(RequestStatusBadge, { status: row.original.status }),
  },
  {
    id: 'created',
    header: 'Created',
    cell: ({ row }) => new Date(row.original.created_at).toLocaleDateString(),
  },
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
            onClick: () => router.push(`/requests/${row.original.id}`),
          },
          () => 'View',
        ),
        canEditRequest(row.original, auth.user?.id, can)
          ? h(
              Button,
              {
                size: 'sm',
                variant: 'ghost',
                onClick: () => router.push(`/requests/${row.original.id}/edit`),
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
    ><PageHeader title="Requests" description="Create and track operational requests."
      ><template v-if="can('requests.create')" #actions
        ><Button as-child
          ><RouterLink to="/requests/create">Create request</RouterLink></Button
        ></template
      ></PageHeader
    >
    <div class="mb-4 flex flex-wrap gap-3">
      <select
        class="h-9 rounded-md border bg-background px-3 text-sm"
        :value="filters.status ?? ''"
        aria-label="Status filter"
        @change="
          update({ status: ($event.target as HTMLSelectElement).value || undefined, page: '1' })
        "
      >
        <option value="">All statuses</option>
        <option
          v-for="status in ['draft', 'submitted', 'approved', 'rejected', 'cancelled']"
          :key="status"
          :value="status"
        >
          {{ status }}
        </option>
      </select>
    </div>
    <p v-if="query.isError.value" class="rounded-md border p-4 text-destructive">
      {{ query.error.value?.message }}
    </p>
    <DataTable
      v-else
      :columns="columns"
      :data="query.data.value?.data ?? []"
      :total="query.data.value?.meta.total ?? 0"
      :page="filters.page"
      :per-page="filters.perPage"
      :loading="query.isPending.value"
      :selectable="false"
      @update:page="(page) => update({ page: String(page) })"
      @update:per-page="(perPage) => update({ per_page: String(perPage), page: '1' })"
  /></AppShell>
</template>

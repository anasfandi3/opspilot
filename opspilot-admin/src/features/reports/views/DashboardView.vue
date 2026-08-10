<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import RequestStatusBadge from '@/features/requests/components/RequestStatusBadge.vue'
import { useWorkspaceStore } from '@/stores/workspace'
import { reportsApi } from '../api/reports'
import { reportKeys } from '../queries/reportKeys'
import MetricCard from '../components/MetricCard.vue'
const workspace = useWorkspaceStore(),
  workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const query = useQuery({
  queryKey: computed(() => reportKeys.dashboard(workspaceId.value)),
  queryFn: () => reportsApi.dashboard(workspaceId.value),
  enabled: () => workspaceId.value > 0,
})
const statuses = ['draft', 'submitted', 'approved', 'rejected', 'cancelled'] as const
</script>
<template>
  <AppShell
    ><PageHeader title="Dashboard" description="Current operational metrics for this workspace." />
    <div v-if="query.isPending.value" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Skeleton v-for="i in 6" :key="i" class="h-28" />
    </div>
    <div v-else-if="query.isError.value" class="rounded-lg border p-8 text-center">
      <p>Unable to load dashboard metrics.</p>
      <Button class="mt-3" @click="query.refetch()">Retry</Button>
    </div>
    <template v-else-if="query.data.value"
      ><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <MetricCard label="Total requests" :value="query.data.value.requests.total" /><MetricCard
          label="Submitted"
          :value="query.data.value.requests.submitted"
        /><MetricCard
          label="Pending approvals"
          :value="query.data.value.approvals.pending"
        /><MetricCard label="Approved" :value="query.data.value.requests.approved" /><MetricCard
          label="Active request types"
          :value="query.data.value.request_types.active"
        /><MetricCard label="Members" :value="query.data.value.members.total" />
      </div>
      <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border bg-card p-5">
          <h2 class="font-semibold">Request status summary</h2>
          <dl class="mt-4 space-y-3">
            <div v-for="status in statuses" :key="status" class="flex justify-between">
              <dt class="capitalize text-muted-foreground">{{ status }}</dt>
              <dd class="font-semibold">{{ query.data.value.requests[status] }}</dd>
            </div>
          </dl>
        </section>
        <section class="rounded-lg border bg-card p-5">
          <h2 class="font-semibold">Recent requests</h2>
          <p
            v-if="!query.data.value.recent_requests.length"
            class="mt-4 text-sm text-muted-foreground"
          >
            No requests yet.
          </p>
          <ul v-else class="mt-3 divide-y">
            <li v-for="request in query.data.value.recent_requests" :key="request.id">
              <RouterLink
                :to="`/requests/${request.id}`"
                class="flex flex-wrap items-center gap-2 py-3 hover:underline"
                ><span class="font-medium">#{{ request.id }} · {{ request.request_type.name }}</span
                ><RequestStatusBadge :status="request.status" /><span
                  class="w-full text-xs text-muted-foreground"
                  >{{ request.creator.name }} ·
                  {{ new Date(request.created_at).toLocaleString() }}</span
                ></RouterLink
              >
            </li>
          </ul>
        </section>
      </div></template
    ></AppShell
  >
</template>

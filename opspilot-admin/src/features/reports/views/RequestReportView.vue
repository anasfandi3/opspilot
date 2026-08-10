<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkspaceStore } from '@/stores/workspace'
import { reportsApi } from '../api/reports'
import { reportKeys } from '../queries/reportKeys'
import {
  filtersAfterWorkspaceSwitch,
  normalizeReportFilters,
  reportQueryForDates,
  resetReportQuery,
} from '../reportFilters'
import { formatDuration, periodLabel } from '../reportFormatting'
import MetricCard from '../components/MetricCard.vue'
import ReportFilters from '../components/ReportFilters.vue'
import TrendBars from '../components/TrendBars.vue'
const route = useRoute(),
  router = useRouter(),
  workspace = useWorkspaceStore(),
  workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const filters = computed(() => normalizeReportFilters(route.query)),
  priorWorkspace = ref(workspaceId.value)
const query = useQuery({
  queryKey: computed(() => reportKeys.requests(workspaceId.value, filters.value)),
  queryFn: () => reportsApi.requests(workspaceId.value, filters.value),
  enabled: () => workspaceId.value > 0,
})
function apply(from: string, to: string) {
  router.replace({ query: reportQueryForDates(filters.value, from, to) })
}
watch(workspaceId, (id) => {
  if (priorWorkspace.value && id !== priorWorkspace.value && filters.value.requestTypeId) {
    const safe = filtersAfterWorkspaceSwitch(filters.value)
    router.replace({ query: { ...safe, request_type_id: undefined } })
  }
  priorWorkspace.value = id
})
const statuses = ['draft', 'submitted', 'approved', 'rejected', 'cancelled'] as const
</script>
<template>
  <AppShell
    ><PageHeader
      title="Request report"
      description="Created-request status and lifecycle events are reported separately."
    /><ReportFilters
      :from="filters.from"
      :to="filters.to"
      @apply="apply"
      @reset="router.replace({ query: resetReportQuery() })"
    />
    <div v-if="query.isPending.value" class="mt-6 grid gap-4 sm:grid-cols-2">
      <Skeleton v-for="i in 6" :key="i" class="h-28" />
    </div>
    <div v-else-if="query.isError.value" class="mt-6 rounded-lg border p-8 text-center">
      <p>Unable to load the request report. Check the selected range and try again.</p>
      <Button class="mt-3" @click="query.refetch()">Retry</Button>
    </div>
    <template v-else-if="query.data.value"
      ><p class="mt-5 text-sm text-muted-foreground">
        Reporting period: {{ periodLabel(query.data.value.period) }}
      </p>
      <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          label="Requests created"
          :value="query.data.value.created.total"
          note="Created during selected period"
        /><MetricCard
          label="Submitted events"
          :value="query.data.value.lifecycle.submitted"
        /><MetricCard
          label="Approved events"
          :value="query.data.value.lifecycle.approved"
        /><MetricCard
          label="Average resolution"
          :value="formatDuration(query.data.value.lifecycle.resolution.average_hours)"
        />
      </div>
      <section class="mt-6 rounded-lg border bg-card p-5">
        <h2 class="font-semibold">Requests created by day</h2>
        <TrendBars
          class="mt-4"
          :rows="
            query.data.value.created.trend.map((row) => ({ label: row.date, value: row.count }))
          "
        />
        <p class="mt-2 text-xs text-muted-foreground">
          Daily counts for every UTC date in the selected period.
        </p>
      </section>
      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border bg-card p-5">
          <h2 class="font-semibold">Current status of requests created in period</h2>
          <dl class="mt-4 space-y-2">
            <div v-for="status in statuses" :key="status" class="flex justify-between">
              <dt class="capitalize">{{ status }}</dt>
              <dd>{{ query.data.value.created.current_status[status] }}</dd>
            </div>
          </dl>
        </section>
        <section class="rounded-lg border bg-card p-5">
          <h2 class="font-semibold">Lifecycle events during period</h2>
          <dl class="mt-4 space-y-2">
            <div
              v-for="item in [
                ['Submitted', query.data.value.lifecycle.submitted],
                ['Approved', query.data.value.lifecycle.approved],
                ['Rejected', query.data.value.lifecycle.rejected],
                ['Cancelled', query.data.value.lifecycle.cancelled],
              ]"
              :key="String(item[0])"
              class="flex justify-between"
            >
              <dt>{{ item[0] }}</dt>
              <dd>{{ item[1] }}</dd>
            </div>
          </dl>
        </section>
        <section class="rounded-lg border bg-card p-5">
          <h2 class="font-semibold">Created by request type</h2>
          <p
            v-if="!query.data.value.created.by_request_type.length"
            class="mt-3 text-sm text-muted-foreground"
          >
            No requests were created in this period.
          </p>
          <ul v-else class="mt-3 space-y-2">
            <li
              v-for="row in query.data.value.created.by_request_type"
              :key="row.request_type.id"
              class="flex justify-between"
            >
              <span>{{ row.request_type.name }}</span
              ><strong>{{ row.count }}</strong>
            </li>
          </ul>
        </section>
        <section class="rounded-lg border bg-card p-5">
          <h2 class="font-semibold">Resolution duration</h2>
          <dl class="mt-3 space-y-2">
            <div class="flex justify-between">
              <dt>Resolved requests</dt>
              <dd>{{ query.data.value.lifecycle.resolution.count }}</dd>
            </div>
            <div class="flex justify-between">
              <dt>Approved average</dt>
              <dd>
                {{ formatDuration(query.data.value.lifecycle.resolution.approved_average_hours) }}
              </dd>
            </div>
            <div class="flex justify-between">
              <dt>Rejected average</dt>
              <dd>
                {{ formatDuration(query.data.value.lifecycle.resolution.rejected_average_hours) }}
              </dd>
            </div>
          </dl>
        </section>
      </div></template
    ></AppShell
  >
</template>

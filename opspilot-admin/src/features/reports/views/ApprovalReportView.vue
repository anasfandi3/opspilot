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
  queryKey: computed(() => reportKeys.approvals(workspaceId.value, filters.value)),
  queryFn: () => reportsApi.approvals(workspaceId.value, filters.value),
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
</script>
<template>
  <AppShell
    ><PageHeader
      title="Approval report"
      description="Current pending workload is separate from date-range decision metrics."
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
      <p>Unable to load the approval report. Check the selected range and try again.</p>
      <Button class="mt-3" @click="query.refetch()">Retry</Button>
    </div>
    <template v-else-if="query.data.value"
      ><p class="mt-5 text-sm text-muted-foreground">
        Decision period: {{ periodLabel(query.data.value.period) }}
      </p>
      <section class="mt-4 rounded-lg border bg-card p-5">
        <h2 class="font-semibold">Current pending workload</h2>
        <p class="text-sm text-muted-foreground">
          Live workload; not limited to the selected decision period.
        </p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <MetricCard
            label="Pending approvals"
            :value="query.data.value.current.pending"
          /><MetricCard
            label="Oldest pending"
            :value="
              query.data.value.current.oldest_pending_activated_at
                ? new Date(query.data.value.current.oldest_pending_activated_at).toLocaleString()
                : 'No pending approvals'
            "
          />
        </div>
      </section>
      <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          label="Decisions"
          :value="query.data.value.decisions.total"
          note="During selected period"
        /><MetricCard label="Approved" :value="query.data.value.decisions.approved" /><MetricCard
          label="Rejected"
          :value="query.data.value.decisions.rejected"
        /><MetricCard
          label="Average decision"
          :value="formatDuration(query.data.value.decisions.average_decision_hours)"
        />
      </div>
      <section class="mt-6 rounded-lg border bg-card p-5">
        <h2 class="font-semibold">Approval decisions by day</h2>
        <TrendBars
          class="mt-4"
          :rows="
            query.data.value.decisions.trend.map((row) => ({
              label: row.date,
              value: row.total,
              detail: `${row.approved} approved, ${row.rejected} rejected`,
            }))
          "
        />
        <p class="mt-2 text-xs text-muted-foreground">
          Daily approved and rejected totals for every UTC date in the selected period.
        </p>
      </section>
      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border bg-card p-5">
          <h2 class="font-semibold">Decision duration</h2>
          <dl class="mt-3 space-y-2">
            <div class="flex justify-between">
              <dt>Approved average</dt>
              <dd>{{ formatDuration(query.data.value.decisions.approved_average_hours) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt>Rejected average</dt>
              <dd>{{ formatDuration(query.data.value.decisions.rejected_average_hours) }}</dd>
            </div>
          </dl>
        </section>
        <section class="rounded-lg border bg-card p-5">
          <h2 class="font-semibold">Decisions by workflow step</h2>
          <p
            v-if="!query.data.value.decisions.by_step.length"
            class="mt-3 text-sm text-muted-foreground"
          >
            No approval decisions occurred in this period.
          </p>
          <ul v-else class="mt-3 space-y-3">
            <li v-for="row in query.data.value.decisions.by_step" :key="row.workflow_step.id">
              <div class="flex justify-between">
                <span>{{ row.workflow_step.name }}</span
                ><strong>{{ row.total }}</strong>
              </div>
              <p class="text-xs text-muted-foreground">
                {{ row.approved }} approved · {{ row.rejected }} rejected
              </p>
            </li>
          </ul>
        </section>
      </div></template
    ></AppShell
  >
</template>

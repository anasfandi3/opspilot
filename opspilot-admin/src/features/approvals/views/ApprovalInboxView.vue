<script setup lang="ts">
import { computed, h, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import type { ColumnDef } from '@tanstack/vue-table'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { toast } from 'vue-sonner'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataTable from '@/components/app/table/DataTable.vue'
import { Button } from '@/components/ui/button'
import { useWorkspaceStore } from '@/stores/workspace'
import { useAuthorization } from '@/composables/useAuthorization'
import { requestKeys } from '@/features/requests/queries/requestKeys'
import { approvalsApi } from '../api/approvals'
import { approvalKeys, type ApprovalFilters } from '../queries/approvalKeys'
import type { ApprovalInboxItem } from '../types/approval'
import ApprovalStatusBadge from '../components/ApprovalStatusBadge.vue'
import ApprovalDecisionDialog from '../components/ApprovalDecisionDialog.vue'
import { canActOnApproval, canApplyApprovalResult, isStaleApprovalError } from '../approvalActions'

const workspace = useWorkspaceStore(),
  route = useRoute(),
  router = useRouter(),
  client = useQueryClient(),
  { can } = useAuthorization()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const filters = computed<ApprovalFilters>(() => ({
  page: Math.max(1, Number(route.query.page) || 1),
  perPage: Math.min(100, Math.max(1, Number(route.query.per_page) || 20)),
  status:
    typeof route.query.status === 'string' && route.query.status ? route.query.status : undefined,
}))
const query = useQuery({
  queryKey: computed(() => approvalKeys.inbox(workspaceId.value, filters.value)),
  queryFn: () => approvalsApi.inbox(workspaceId.value, filters.value),
  enabled: computed(() => workspaceId.value > 0),
})
const target = ref<{ approval: ApprovalInboxItem; decision: 'approve' | 'reject' } | null>(null)
const pageError = ref('')
watch(workspaceId, () => {
  target.value = null
  pageError.value = ''
})
function update(values: Record<string, string | undefined>) {
  const next = { ...route.query, ...values }
  Object.keys(next).forEach((key) => next[key] === undefined && delete next[key])
  void router.replace({ query: next })
}
const decision = useMutation({
  mutationFn: (input: {
    workspaceId: number
    approvalId: number
    requestId: number
    decision: 'approve' | 'reject'
  }) =>
    input.decision === 'approve'
      ? approvalsApi.approve(input.workspaceId, input.approvalId)
      : approvalsApi.reject(input.workspaceId, input.approvalId),
  onSuccess: async (_, input) => {
    if (!canApplyApprovalResult(input.workspaceId, workspaceId.value)) return
    target.value = null
    await Promise.all([
      client.invalidateQueries({ queryKey: approvalKeys.all(input.workspaceId) }),
      client.invalidateQueries({
        queryKey: requestKeys.detail(input.workspaceId, input.requestId),
      }),
      client.invalidateQueries({ queryKey: requestKeys.all(input.workspaceId) }),
    ])
    toast.success(input.decision === 'approve' ? 'Approval approved' : 'Approval rejected')
  },
  onError: async (error, input) => {
    if (!canApplyApprovalResult(input.workspaceId, workspaceId.value)) return
    if (isStaleApprovalError(error)) {
      target.value = null
      pageError.value = 'This approval is no longer actionable. The latest state has been loaded.'
      await Promise.all([
        client.invalidateQueries({ queryKey: approvalKeys.all(input.workspaceId) }),
        client.invalidateQueries({
          queryKey: requestKeys.detail(input.workspaceId, input.requestId),
        }),
      ])
    }
  },
})
const columns: ColumnDef<ApprovalInboxItem>[] = [
  {
    id: 'request',
    header: 'Request',
    cell: ({ row }) =>
      h('div', [
        h(
          'p',
          { class: 'font-medium' },
          `#${row.original.request.id} · ${row.original.request.request_type.name}`,
        ),
        h('p', { class: 'text-xs text-muted-foreground' }, row.original.request.creator.name),
      ]),
  },
  {
    id: 'step',
    header: 'Approval step',
    cell: ({ row }) => `${row.original.position}. ${row.original.workflow_step.name}`,
  },
  {
    id: 'status',
    header: 'Status',
    cell: ({ row }) => h(ApprovalStatusBadge, { status: row.original.status }),
  },
  {
    id: 'activated',
    header: 'Activated',
    cell: ({ row }) =>
      row.original.activated_at ? new Date(row.original.activated_at).toLocaleString() : '—',
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
            onClick: () => router.push(`/approvals/${row.original.id}`),
          },
          () => 'Review',
        ),
        canActOnApproval(row.original.status, can)
          ? h(
              Button,
              {
                size: 'sm',
                onClick: () => (target.value = { approval: row.original, decision: 'approve' }),
              },
              () => 'Approve',
            )
          : null,
        canActOnApproval(row.original.status, can)
          ? h(
              Button,
              {
                size: 'sm',
                variant: 'destructive',
                onClick: () => (target.value = { approval: row.original, decision: 'reject' }),
              },
              () => 'Reject',
            )
          : null,
      ]),
  },
]
</script>
<template>
  <AppShell
    ><PageHeader title="Approvals" description="Review approvals assigned to you."
      ><template #actions
        ><Button as-child variant="outline"
          ><RouterLink to="/requests">View requests</RouterLink></Button
        ></template
      ></PageHeader
    >
    <p
      v-if="pageError"
      class="mb-4 rounded-md border border-destructive/40 p-4 text-sm text-destructive"
    >
      {{ pageError }}
    </p>
    <div class="mb-4">
      <select
        class="h-9 rounded-md border bg-background px-3 text-sm"
        :value="filters.status ?? ''"
        aria-label="Approval status"
        @change="
          update({ status: ($event.target as HTMLSelectElement).value || undefined, page: '1' })
        "
      >
        <option value="">Pending approvals</option>
        <option
          v-for="status in ['waiting', 'pending', 'approved', 'rejected', 'cancelled']"
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
      @update:per-page="
        (perPage) => update({ per_page: String(perPage), page: '1' })
      " /><ApprovalDecisionDialog
      v-if="target"
      :open="true"
      :decision="target.decision"
      :request-id="target.approval.request.id"
      :step-name="target.approval.workflow_step.name"
      :loading="decision.isPending.value"
      :error="
        !isStaleApprovalError(decision.error.value) && decision.error.value instanceof Error
          ? decision.error.value.message
          : ''
      "
      @update:open="(open) => !open && (target = null)"
      @confirm="
        decision.mutate({
          workspaceId,
          approvalId: target.approval.id,
          requestId: target.approval.request.id,
          decision: target.decision,
        })
      "
  /></AppShell>
</template>

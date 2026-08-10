<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import LoadingState from '@/components/app/feedback/LoadingState.vue'
import { Button } from '@/components/ui/button'
import { useWorkspaceStore } from '@/stores/workspace'
import { useAuthorization } from '@/composables/useAuthorization'
import RequestStatusBadge from '@/features/requests/components/RequestStatusBadge.vue'
import RequestValues from '@/features/requests/components/RequestValues.vue'
import ApprovalPlanSummary from '@/features/requests/components/ApprovalPlanSummary.vue'
import { requestKeys } from '@/features/requests/queries/requestKeys'
import { approvalsApi } from '../api/approvals'
import { approvalKeys } from '../queries/approvalKeys'
import ApprovalStatusBadge from '../components/ApprovalStatusBadge.vue'
import ApprovalDecisionDialog from '../components/ApprovalDecisionDialog.vue'
import {
  approvalWorkspaceDestination,
  canActOnApproval,
  canApplyApprovalResult,
  isStaleApprovalError,
} from '../approvalActions'

const workspace = useWorkspaceStore(),
  route = useRoute(),
  router = useRouter(),
  client = useQueryClient(),
  { can } = useAuthorization()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0),
  approvalId = computed(() => Number(route.params.id) || 0)
const query = useQuery({
  queryKey: computed(() => approvalKeys.detail(workspaceId.value, approvalId.value)),
  queryFn: () => approvalsApi.detail(workspaceId.value, approvalId.value),
  enabled: computed(() => workspaceId.value > 0 && approvalId.value > 0),
})
const openDecision = ref<'approve' | 'reject' | null>(null),
  pageError = ref('')
watch(workspaceId, (_, previous) => {
  openDecision.value = null
  pageError.value = ''
  if (previous && approvalWorkspaceDestination(route.name)) void router.replace('/approvals')
})
const mutation = useMutation({
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
    openDecision.value = null
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
      openDecision.value = null
      pageError.value = 'This approval is no longer actionable. The latest state has been loaded.'
      await Promise.all([
        query.refetch(),
        client.invalidateQueries({ queryKey: approvalKeys.all(input.workspaceId) }),
        client.invalidateQueries({
          queryKey: requestKeys.detail(input.workspaceId, input.requestId),
        }),
      ])
    }
  },
})
const fields = computed(() => query.data.value?.request.definition_snapshot?.fields ?? [])
</script>
<template>
  <AppShell
    ><LoadingState v-if="query.isPending.value" label="Loading approval" />
    <div v-else-if="query.isError.value" class="rounded-md border p-6 text-destructive">
      <p>{{ query.error.value?.message }}</p>
      <Button class="mt-3" variant="outline" @click="query.refetch()">Retry</Button>
    </div>
    <template v-else-if="query.data.value"
      ><PageHeader
        :title="`Approval for request #${query.data.value.request.id}`"
        :description="query.data.value.workflow_step.name"
        ><template #actions
          ><Button as-child variant="outline"
            ><RouterLink :to="`/requests/${query.data.value.request.id}`"
              >View request</RouterLink
            ></Button
          ><Button
            v-if="canActOnApproval(query.data.value.status, can)"
            @click="openDecision = 'approve'"
            >Approve</Button
          ><Button
            v-if="canActOnApproval(query.data.value.status, can)"
            variant="destructive"
            @click="openDecision = 'reject'"
            >Reject</Button
          ></template
        ></PageHeader
      >
      <p
        v-if="pageError"
        class="mb-5 rounded-md border border-destructive/40 p-4 text-sm text-destructive"
      >
        {{ pageError }}
      </p>
      <section class="mb-6 grid gap-4 rounded-lg border p-5 sm:grid-cols-4">
        <div>
          <p class="text-xs text-muted-foreground">Approval</p>
          <ApprovalStatusBadge class="mt-1" :status="query.data.value.status" />
        </div>
        <div>
          <p class="text-xs text-muted-foreground">Request status</p>
          <RequestStatusBadge class="mt-1" :status="query.data.value.request.status" />
        </div>
        <div>
          <p class="text-xs text-muted-foreground">Requester</p>
          <p>{{ query.data.value.request.creator.name }}</p>
        </div>
        <div>
          <p class="text-xs text-muted-foreground">Historical assignees</p>
          <p>
            {{ query.data.value.assignees.map((item) => item.name).join(', ') || 'None recorded' }}
          </p>
        </div>
      </section>
      <section class="mb-8">
        <h2 class="mb-4 text-lg font-semibold">Request values</h2>
        <RequestValues :fields="fields" :payload="query.data.value.request.payload" />
      </section>
      <section>
        <h2 class="mb-4 text-lg font-semibold">Approval plan</h2>
        <ApprovalPlanSummary :approvals="query.data.value.request.approvals" />
      </section>
      <ApprovalDecisionDialog
        v-if="openDecision"
        :open="true"
        :decision="openDecision"
        :request-id="query.data.value.request.id"
        :step-name="query.data.value.workflow_step.name"
        :loading="mutation.isPending.value"
        :error="
          !isStaleApprovalError(mutation.error.value) && mutation.error.value instanceof Error
            ? mutation.error.value.message
            : ''
        "
        @update:open="(open) => !open && (openDecision = null)"
        @confirm="
          mutation.mutate({
            workspaceId,
            approvalId,
            requestId: query.data.value!.request.id,
            decision: openDecision!,
          })
        " /></template
  ></AppShell>
</template>

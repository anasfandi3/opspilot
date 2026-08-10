<script setup lang="ts">
import { computed, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { toast } from 'vue-sonner'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import LoadingState from '@/components/app/feedback/LoadingState.vue'
import { Button } from '@/components/ui/button'
import ConfirmDialog from '@/components/app/ConfirmDialog.vue'
import { useAuthorization } from '@/composables/useAuthorization'
import { useWorkspaceStore } from '@/stores/workspace'
import { useAuthStore } from '@/stores/auth'
import { requestsApi } from '../api/requests'
import { requestKeys } from '../queries/requestKeys'
import RequestStatusBadge from '../components/RequestStatusBadge.vue'
import RequestValues from '../components/RequestValues.vue'
import ApprovalPlanSummary from '../components/ApprovalPlanSummary.vue'
import { requestWorkspaceDestination } from '../workspaceBehavior'
import { canApplyRequestResult } from '../workspaceBehavior'
import { canCancelRequest, canEditRequest } from '../requestActions'
import { canCollaborateOnRequest } from '../requestActions'
import CollaborationSections from '../collaboration/CollaborationSections.vue'
const route = useRoute(),
  router = useRouter(),
  workspace = useWorkspaceStore(),
  auth = useAuthStore(),
  client = useQueryClient(),
  { can } = useAuthorization()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0),
  id = computed(() => Number(route.params.id) || 0)
const query = useQuery({
  queryKey: computed(() => requestKeys.detail(workspaceId.value, id.value)),
  queryFn: () => requestsApi.detail(workspaceId.value, id.value),
  enabled: computed(() => workspaceId.value > 0 && id.value > 0),
})
watch(workspaceId, (_, old) => {
  if (old && requestWorkspaceDestination(route.name)) void router.replace('/requests')
})
const fields = computed(() => query.data.value?.definition_snapshot?.fields ?? [])
const cancelMutation = useMutation({
  mutationFn: (input: { workspaceId: number; requestId: number }) =>
    requestsApi.cancel(input.workspaceId, input.requestId),
  onSuccess: async (request, input) => {
    if (!canApplyRequestResult(input.workspaceId, workspaceId.value)) return
    client.setQueryData(requestKeys.detail(input.workspaceId, input.requestId), request)
    await client.invalidateQueries({ queryKey: requestKeys.all(input.workspaceId) })
    toast.success('Request cancelled')
  },
})
const actionError = computed(() =>
  cancelMutation.error.value instanceof Error ? cancelMutation.error.value.message : '',
)
</script>
<template>
  <AppShell
    ><LoadingState v-if="query.isPending.value" label="Loading request" />
    <p v-else-if="query.isError.value" class="rounded-md border p-6 text-destructive">
      {{ query.error.value?.message }}
    </p>
    <template v-else-if="query.data.value"
      ><PageHeader
        :title="`Request #${query.data.value.id}`"
        :description="query.data.value.request_type.name"
        ><template #actions
          ><Button v-if="canEditRequest(query.data.value, auth.user?.id, can)" as-child
            ><RouterLink :to="`/requests/${query.data.value.id}/edit`"
              >Edit draft</RouterLink
            ></Button
          >
          <ConfirmDialog
            v-if="canCancelRequest(query.data.value, auth.user?.id, can)"
            title="Cancel request?"
            description="This cancels the request and any active approval work. This action cannot be undone."
            confirm-text="Cancel request"
            destructive
            :loading="cancelMutation.isPending.value"
            @confirm="cancelMutation.mutate({ workspaceId, requestId: query.data.value!.id })"
            ><template #trigger
              ><Button variant="destructive">Cancel request</Button></template
            ></ConfirmDialog
          ></template
        ></PageHeader
      >
      <p
        v-if="actionError"
        class="mb-6 rounded-md border border-destructive/40 p-4 text-sm text-destructive"
      >
        {{ actionError }}
      </p>
      <section class="mb-6 grid gap-4 rounded-lg border p-5 sm:grid-cols-3">
        <div>
          <p class="text-xs text-muted-foreground">Status</p>
          <RequestStatusBadge class="mt-1" :status="query.data.value.status" />
        </div>
        <div>
          <p class="text-xs text-muted-foreground">Requester</p>
          <p>{{ query.data.value.creator.name }}</p>
        </div>
        <div>
          <p class="text-xs text-muted-foreground">Workflow</p>
          <p>
            {{
              query.data.value.workflow
                ? `${query.data.value.workflow.name} v${query.data.value.workflow.version}`
                : 'Not submitted'
            }}
          </p>
        </div>
      </section>
      <section class="mb-8">
        <h2 class="mb-4 text-lg font-semibold">Request values</h2>
        <RequestValues :fields="fields" :payload="query.data.value.payload" />
      </section>
      <section>
        <h2 class="mb-4 text-lg font-semibold">Approval plan</h2>
        <ApprovalPlanSummary :approvals="query.data.value.approvals" />
      </section>
      <CollaborationSections
        :request-id="query.data.value.id"
        :can-collaborate="
          canCollaborateOnRequest(
            query.data.value,
            auth.user?.id,
            workspace.currentWorkspace?.role,
            can,
          )
        " /></template
  ></AppShell>
</template>

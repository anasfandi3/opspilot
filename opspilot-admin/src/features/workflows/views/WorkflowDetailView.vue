<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import LoadingState from '@/components/app/feedback/LoadingState.vue'
import ConfirmDialog from '@/components/app/ConfirmDialog.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useWorkspaceStore } from '@/stores/workspace'
import { useAuthorization } from '@/composables/useAuthorization'
import { requestTypesApi } from '@/features/request-types/api/requestTypes'
import { requestTypeKeys } from '@/features/request-types/queries/requestTypeKeys'
import { workflowsApi } from '../api/workflows'
import { workflowKeys } from '../queries/workflowKeys'
import { formatCondition } from '../conditions'
import { canApplyWorkflowResult } from '../workflowForm'
import { workflowWorkspaceDestination } from '../workspaceBehavior'
const route = useRoute()
const router = useRouter()
const workspace = useWorkspaceStore()
const client = useQueryClient()
const { can } = useAuthorization()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const workflowId = computed(() => Number(route.params.id) || 0)
const publishOpen = ref(false)
const types = useQuery({
  queryKey: computed(() => requestTypeKeys.list(workspaceId.value)),
  queryFn: () => requestTypesApi.list(workspaceId.value),
  enabled: computed(() => workspaceId.value > 0),
})
const all = useQuery({
  queryKey: computed(() => workflowKeys.list(workspaceId.value)),
  queryFn: () => workflowsApi.all(workspaceId.value, types.data.value ?? []),
  enabled: computed(() => !!types.data.value),
})
const item = computed(() => all.data.value?.find((entry) => entry.id === workflowId.value))
const versions = computed(
  () =>
    all.data.value?.filter((entry) => entry.requestType.id === item.value?.requestType.id) ?? [],
)
watch(workspaceId, (current, previous) => {
  publishOpen.value = false
  const destination = workflowWorkspaceDestination(route.name)
  if (previous && current !== previous && destination) void router.replace(destination)
})
const publish = useMutation({
  mutationFn: (input: { workspaceId: number; requestTypeId: number; workflowId: number }) =>
    workflowsApi.publish(input.workspaceId, input.requestTypeId, input.workflowId),
  onSuccess: async (_, input) => {
    if (!canApplyWorkflowResult(input.workspaceId, workspaceId.value)) return
    publishOpen.value = false
    await client.invalidateQueries({ queryKey: workflowKeys.list(input.workspaceId) })
    toast.success('Workflow published')
  },
  onError: (error, input) => {
    if (!canApplyWorkflowResult(input.workspaceId, workspaceId.value)) return
    toast.error(error instanceof Error ? error.message : 'Unable to publish workflow.')
  },
})
function confirmPublish() {
  if (item.value)
    publish.mutate({
      workspaceId: workspaceId.value,
      requestTypeId: item.value.requestType.id,
      workflowId: item.value.id,
    })
}
function retry() {
  void Promise.all([types.refetch(), all.refetch()])
}
</script>
<template>
  <AppShell
    ><LoadingState v-if="types.isPending.value || all.isPending.value" label="Loading workflow" />
    <div
      v-else-if="all.isError.value || types.isError.value"
      class="rounded-lg border p-6 text-sm text-destructive"
    >
      <p>{{ all.error.value?.message || types.error.value?.message }}</p>
      <Button class="mt-3" variant="outline" @click="retry">Retry</Button>
    </div>
    <template v-else-if="item"
      ><PageHeader :title="item.name" :description="item.description || 'No description provided.'"
        ><template #actions
          ><Button variant="outline" as-child><RouterLink to="/workflows">Back</RouterLink></Button
          ><Button v-if="can('workflows.manage') && item.status === 'draft'" as-child
            ><RouterLink :to="`/workflows/${item.id}/edit`">Edit draft</RouterLink></Button
          ><Button
            v-if="can('workflows.manage') && item.status === 'draft'"
            :disabled="!item.steps.length"
            @click="publishOpen = true"
            >Publish version</Button
          ><Button
            v-if="can('workflows.manage') && item.status !== 'draft'"
            @click="router.push(`/workflows/${item.id}/edit?clone=1`)"
            >Create new draft</Button
          ></template
        ></PageHeader
      >
      <div class="mb-8 grid gap-4 rounded-lg border bg-card p-5 text-sm sm:grid-cols-4">
        <div>
          <p class="text-muted-foreground">Request type</p>
          <p class="font-medium">{{ item.requestType.name }}</p>
        </div>
        <div>
          <p class="text-muted-foreground">Version</p>
          <p class="font-medium">v{{ item.version }}</p>
        </div>
        <div>
          <p class="text-muted-foreground">Status</p>
          <Badge :variant="item.status === 'active' ? 'default' : 'secondary'">{{
            item.status
          }}</Badge>
        </div>
        <div>
          <p class="text-muted-foreground">Published</p>
          <p class="font-medium">
            {{ item.published_at ? new Date(item.published_at).toLocaleString() : 'Not published' }}
          </p>
        </div>
      </div>
      <section class="space-y-4">
        <h2 class="text-xl font-semibold">Approval flow</h2>
        <p
          v-if="!item.steps.length"
          class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
          No approval steps configured.
        </p>
        <template v-for="(step, index) in item.steps" :key="step.id"
          ><Card
            ><CardHeader
              ><CardTitle class="text-base"
                >Step {{ index + 1 }} · {{ step.name }}</CardTitle
              ></CardHeader
            ><CardContent class="space-y-3 text-sm"
              ><p>
                <span class="text-muted-foreground">Approver:</span>
                {{
                  step.approver_type === 'role'
                    ? `${step.approver_role} role`
                    : step.approver_user?.name
                }}
              </p>
              <div>
                <p class="font-medium">
                  {{
                    step.conditions.length
                      ? step.condition_logic === 'all'
                        ? 'All conditions must match'
                        : 'Any condition may match'
                      : 'Always runs'
                  }}
                </p>
                <ul
                  v-if="step.conditions.length"
                  class="mt-2 list-inside list-disc text-muted-foreground"
                >
                  <li v-for="condition in step.conditions" :key="condition.id">
                    {{ formatCondition(condition, item.requestType.fields) }}
                  </li>
                </ul>
              </div></CardContent
            ></Card
          >
          <div
            v-if="index < item.steps.length - 1"
            class="text-center text-muted-foreground"
            aria-hidden="true"
          >
            ↓
          </div></template
        >
      </section>
      <section class="mt-10">
        <h2 class="mb-4 text-xl font-semibold">Version history</h2>
        <div class="space-y-2">
          <RouterLink
            v-for="version in versions"
            :key="version.id"
            :to="`/workflows/${version.id}`"
            class="flex items-center justify-between rounded-lg border p-4 hover:bg-muted/40"
            ><span>Version {{ version.version }} · {{ version.name }}</span
            ><Badge variant="outline">{{ version.status }}</Badge></RouterLink
          >
        </div>
      </section>
      <ConfirmDialog
        v-model:open="publishOpen"
        title="Publish this workflow version?"
        :description="`Publish version ${item.version}? It becomes immutable and replaces the currently active version.`"
        confirm-text="Publish"
        :loading="publish.isPending.value"
        @confirm="confirmPublish"
    /></template>
    <div v-else class="rounded-lg border p-6">Workflow not found.</div></AppShell
  >
</template>

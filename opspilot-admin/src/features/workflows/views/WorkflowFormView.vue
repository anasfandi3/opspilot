<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import LoadingState from '@/components/app/feedback/LoadingState.vue'
import { Button } from '@/components/ui/button'
import { useDirtyState } from '@/composables/useDirtyState'
import { useAuthorization } from '@/composables/useAuthorization'
import { useWorkspaceStore } from '@/stores/workspace'
import { requestTypesApi } from '@/features/request-types/api/requestTypes'
import { requestTypeKeys } from '@/features/request-types/queries/requestTypeKeys'
import { membersApi } from '@/features/members/api/members'
import { membersKeys } from '@/features/members/queries/memberKeys'
import WorkflowForm from '../components/WorkflowForm.vue'
import { workflowsApi } from '../api/workflows'
import { workflowKeys } from '../queries/workflowKeys'
import {
  apiFieldErrors,
  canApplyWorkflowResult,
  hydrateWorkflow,
  PartialWorkflowSaveError,
  persistWorkflowForm,
  validateWorkflowForm,
} from '../workflowForm'
import { resetWorkflowWorkspaceForm, workflowWorkspaceDestination } from '../workspaceBehavior'
import type { Workflow, WorkflowFormModel } from '../types/workflow'
const route = useRoute()
const router = useRouter()
const workspace = useWorkspaceStore()
const client = useQueryClient()
const { can } = useAuthorization()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const workflowId = computed(() => Number(route.params.id) || 0)
const editing = computed(() => route.name === 'workflows-edit')
const form = ref(hydrateWorkflow())
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const hydrated = ref(!editing.value)
const cloneStarted = ref(false)
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
const members = useQuery({
  queryKey: computed(() => membersKeys.list(workspaceId.value)),
  queryFn: () => membersApi.list(workspaceId.value),
  enabled: computed(() => workspaceId.value > 0 && can('members.view')),
})
const original = computed(() => all.data.value?.find((item) => item.id === workflowId.value))
const availableTypes = computed(() =>
  editing.value
    ? (types.data.value ?? [])
    : (types.data.value ?? []).filter(
        (type) => !all.data.value?.some((item) => item.requestType.id === type.id),
      ),
)
const { markClean, forceDiscard } = useDirtyState(form)
watch(workspaceId, (current, previous) => {
  const destination = workflowWorkspaceDestination(route.name)
  if (previous && current !== previous && destination) {
    forceDiscard()
    const reset = resetWorkflowWorkspaceForm()
    form.value = reset.form
    errors.value = reset.errors
    generalError.value = reset.generalError
    hydrated.value = true
    void router.replace(destination)
  }
})
const clone = useMutation({
  mutationFn: (input: { workspaceId: number; requestTypeId: number; workflowId: number }) =>
    workflowsApi.clone(input.workspaceId, input.requestTypeId, input.workflowId),
  onSuccess: async (value, input) => {
    if (!canApplyWorkflowResult(input.workspaceId, workspaceId.value)) return
    await client.invalidateQueries({ queryKey: workflowKeys.list(input.workspaceId) })
    forceDiscard()
    await router.replace(`/workflows/${value.id}/edit`)
  },
  onError: (error) =>
    (generalError.value =
      error instanceof Error ? error.message : 'Unable to create a draft version.'),
})
watch(
  original,
  async (value) => {
    if (!editing.value || !value) return
    if (value.status !== 'draft') {
      if (route.query.clone === '1' && !cloneStarted.value) {
        cloneStarted.value = true
        clone.mutate({
          workspaceId: workspaceId.value,
          requestTypeId: value.requestType.id,
          workflowId: value.id,
        })
      } else {
        hydrated.value = true
      }
      return
    }
    form.value = hydrateWorkflow(value, value.requestType.id)
    hydrated.value = true
    await nextTick()
    markClean()
  },
  { immediate: true },
)
interface SaveInput {
  workspaceId: number
  form: WorkflowFormModel
  original?: Workflow
}
const saveMutation = useMutation({
  mutationFn: (input: SaveInput) =>
    persistWorkflowForm(input.workspaceId, input.form, input.original),
  onSuccess: async (value, input) => {
    if (!canApplyWorkflowResult(input.workspaceId, workspaceId.value)) return
    markClean()
    await client.invalidateQueries({ queryKey: workflowKeys.list(input.workspaceId) })
    toast.success(input.original ? 'Workflow updated' : 'Workflow created')
    await router.replace(`/workflows/${value.id}`)
  },
  onError: async (error, input) => {
    if (!canApplyWorkflowResult(input.workspaceId, workspaceId.value)) return
    if (error instanceof PartialWorkflowSaveError) {
      form.value = hydrateWorkflow(error.workflow, input.form.request_type_id)
      errors.value = {}
      generalError.value = `${error.message}${error.cause instanceof Error ? ` ${error.cause.message}` : ''}`
      client.setQueryData(
        workflowKeys.detail(input.workspaceId, input.form.request_type_id!, error.workflow.id),
        error.workflow,
      )
      await client.invalidateQueries({ queryKey: workflowKeys.list(input.workspaceId) })
      await nextTick()
      markClean()
      if (error.created) {
        forceDiscard()
        await router.replace(`/workflows/${error.workflow.id}/edit`)
      }
      return
    }
    errors.value = apiFieldErrors(error)
    generalError.value = error instanceof Error ? error.message : 'Unable to save workflow.'
  },
})
function save() {
  errors.value = validateWorkflowForm(
    form.value,
    types.data.value?.find((type) => type.id === form.value.request_type_id),
  )
  generalError.value = ''
  if (Object.keys(errors.value).length) return
  saveMutation.mutate({
    workspaceId: workspaceId.value,
    form: JSON.parse(JSON.stringify(form.value)) as WorkflowFormModel,
    original: original.value ? (JSON.parse(JSON.stringify(original.value)) as Workflow) : undefined,
  })
}
</script>
<template>
  <AppShell
    ><PageHeader
      :title="editing ? 'Edit workflow draft' : 'Create workflow'"
      description="Configure metadata, sequential approvers, and field-based conditions."
      ><template #actions
        ><Button variant="outline" @click="router.push('/workflows')">Cancel</Button></template
      ></PageHeader
    >
    <LoadingState
      v-if="
        types.isPending.value ||
        all.isPending.value ||
        (can('members.view') && members.isPending.value) ||
        (editing && !hydrated)
      "
      label="Loading workflow builder"
    />
    <div
      v-else-if="editing && original && original.status !== 'draft'"
      class="rounded-lg border p-6"
    >
      <h2 class="font-semibold">Published versions are immutable</h2>
      <p class="mt-2 text-sm text-muted-foreground">
        Create a new draft from this version to make changes.
      </p>
      <p v-if="generalError" class="mt-3 text-sm text-destructive">{{ generalError }}</p>
      <Button
        class="mt-4"
        :disabled="clone.isPending.value"
        @click="
          clone.mutate({
            workspaceId,
            requestTypeId: original.requestType.id,
            workflowId: original.id,
          })
        "
        >{{ clone.isPending.value ? 'Creating draft…' : 'Create new draft' }}</Button
      >
    </div>
    <WorkflowForm
      v-else
      v-model="form"
      :request-types="availableTypes"
      :members="members.data.value ?? []"
      :members-available="can('members.view')"
      :errors="errors"
      :general-error="generalError"
      :saving="saveMutation.isPending.value"
      :editing="editing"
      @submit="save"
      @structural="errors = {}"
    />
  </AppShell>
</template>

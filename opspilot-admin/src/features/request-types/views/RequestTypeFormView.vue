<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import LoadingState from '@/components/app/feedback/LoadingState.vue'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api/errors'
import { useDirtyState } from '@/composables/useDirtyState'
import { useWorkspaceStore } from '@/stores/workspace'
import RequestTypeForm from '../components/RequestTypeForm.vue'
import { hydrateRequestType } from '../fieldTypes'
import { requestTypesApi } from '../api/requestTypes'
import { requestTypeKeys } from '../queries/requestTypeKeys'
import {
  canApplySaveResult,
  mapFieldSaveErrors,
  PartialRequestTypeSaveError,
  partialSaveMessage,
  persistRequestTypeForm,
  RequestTypeFieldSaveError,
  validateRequestTypeForm,
} from '../requestTypeForm'
import type { RequestType, RequestTypeFormModel } from '../types/requestType'
import {
  requestTypeWorkspaceSwitchDestination,
  resetRequestTypeWorkspaceForm,
} from '../workspaceBehavior'

const route = useRoute()
const router = useRouter()
const workspace = useWorkspaceStore()
const client = useQueryClient()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const requestTypeId = computed(() => Number(route.params.id) || 0)
const editing = computed(() => route.name === 'request-types-edit')
const form = ref(hydrateRequestType())
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const hydrated = ref(!editing.value)
const query = useQuery({
  queryKey: computed(() => requestTypeKeys.detail(workspaceId.value, requestTypeId.value)),
  queryFn: () => requestTypesApi.detail(workspaceId.value, requestTypeId.value),
  enabled: computed(() => editing.value && workspaceId.value > 0 && requestTypeId.value > 0),
})
const { markClean, forceDiscard } = useDirtyState(form)
watch(
  () => query.data.value,
  async (requestType) => {
    if (!requestType) return
    form.value = hydrateRequestType(requestType)
    hydrated.value = true
    await nextTick()
    markClean()
  },
  { immediate: true },
)
watch(workspaceId, (current, previous) => {
  const destination = requestTypeWorkspaceSwitchDestination(route.name)
  if (previous && current !== previous && destination) {
    forceDiscard()
    const reset = resetRequestTypeWorkspaceForm()
    form.value = reset.form
    errors.value = reset.errors
    generalError.value = reset.generalError
    hydrated.value = true
    void router.replace(destination)
  }
})
watch(
  () => query.error.value,
  (error) => error instanceof ApiError && error.kind === 'not-found' && router.replace('/404'),
)
interface SaveInput {
  workspaceId: number
  form: RequestTypeFormModel
  original?: RequestType
}

const mutation = useMutation({
  mutationFn: (input: SaveInput) =>
    persistRequestTypeForm(input.workspaceId, input.form, input.original),
  onSuccess: async (requestType, input) => {
    if (!canApplySaveResult(input.workspaceId, workspaceId.value)) return
    markClean()
    client.setQueryData(requestTypeKeys.detail(input.workspaceId, requestType.id), requestType)
    await client.invalidateQueries({ queryKey: requestTypeKeys.list(input.workspaceId) })
    toast.success(editing.value ? 'Request type updated' : 'Request type created')
    await router.replace(`/request-types/${requestType.id}`)
  },
  onError: async (error, input) => {
    if (!canApplySaveResult(input.workspaceId, workspaceId.value)) return
    if (error instanceof PartialRequestTypeSaveError) {
      form.value = hydrateRequestType(error.requestType)
      errors.value = {}
      generalError.value = partialSaveMessage(error)
      client.setQueryData(
        requestTypeKeys.detail(input.workspaceId, error.requestType.id),
        error.requestType,
      )
      await nextTick()
      markClean()
      if (error.created) {
        forceDiscard()
        await router.replace(`/request-types/${error.requestType.id}/edit`)
      }
      return
    }
    if (error instanceof RequestTypeFieldSaveError) errors.value = mapFieldSaveErrors(error)
    else if (error instanceof ApiError) errors.value = error.fieldErrors
    generalError.value = error instanceof Error ? error.message : 'Unable to save request type.'
  },
})
function save() {
  errors.value = validateRequestTypeForm(form.value)
  generalError.value = ''
  if (Object.keys(errors.value).length) return
  mutation.mutate({
    workspaceId: workspaceId.value,
    form: JSON.parse(JSON.stringify(form.value)) as RequestTypeFormModel,
    original: query.data.value
      ? (JSON.parse(JSON.stringify(query.data.value)) as RequestType)
      : undefined,
  })
}
</script>
<template>
  <AppShell
    ><PageHeader
      :title="editing ? 'Edit request type' : 'Create request type'"
      :description="
        editing
          ? 'Update metadata, fields, options, and ordering.'
          : 'Define a reusable request schema for this workspace.'
      "
      ><template #actions
        ><Button variant="outline" @click="router.push('/request-types')">Cancel</Button></template
      ></PageHeader
    >
    <LoadingState
      v-if="editing && (!hydrated || query.isPending.value)"
      label="Loading request type"
    />
    <div
      v-else-if="
        query.isError.value &&
        !(query.error.value instanceof ApiError && query.error.value.kind === 'not-found')
      "
      class="rounded-lg border p-6"
    >
      <p class="text-sm text-destructive">{{ query.error.value?.message }}</p>
      <Button class="mt-4" variant="outline" @click="query.refetch()">Retry</Button>
    </div>
    <RequestTypeForm
      v-else
      v-model="form"
      :errors="errors"
      :general-error="generalError"
      :saving="mutation.isPending.value"
      @submit="save"
      @structural="errors = {}"
    />
  </AppShell>
</template>

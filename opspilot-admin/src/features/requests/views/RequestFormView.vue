<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import LoadingState from '@/components/app/feedback/LoadingState.vue'
import { useDirtyState } from '@/composables/useDirtyState'
import { useAuthorization } from '@/composables/useAuthorization'
import { useWorkspaceStore } from '@/stores/workspace'
import { useAuthStore } from '@/stores/auth'
import { ApiError } from '@/lib/api/errors'
import RequestForm from '../components/RequestForm.vue'
import { requestsApi } from '../api/requests'
import { requestKeys } from '../queries/requestKeys'
import { initializeValues, mapPayloadErrors, serializeValues, validateValues } from '../fieldValues'
import type { RequestPayload } from '../types/request'
import { canApplyRequestResult, requestWorkspaceDestination } from '../workspaceBehavior'
import {
  canEditRequest,
  canSubmitRequest,
  PartialRequestSubmitError,
  persistRequest,
  type RequestPersistenceInput,
} from '../requestActions'
const route = useRoute(),
  router = useRouter(),
  workspace = useWorkspaceStore(),
  auth = useAuthStore(),
  client = useQueryClient(),
  { can } = useAuthorization()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0),
  requestId = computed(() => Number(route.params.id) || 0),
  editing = computed(() => route.name === 'requests-edit')
const selectedTypeId = ref<number | null>(null),
  values = ref<RequestPayload>({}),
  errors = ref<Record<string, string[]>>({}),
  generalError = ref(''),
  hydrated = ref(!editing.value)
const catalog = useQuery({
  queryKey: computed(() => requestKeys.catalog(workspaceId.value)),
  queryFn: () => requestsApi.catalog(workspaceId.value),
  enabled: computed(() => workspaceId.value > 0),
})
const detail = useQuery({
  queryKey: computed(() => requestKeys.detail(workspaceId.value, requestId.value)),
  queryFn: () => requestsApi.detail(workspaceId.value, requestId.value),
  enabled: computed(() => workspaceId.value > 0 && editing.value),
})
const selectedType = computed(() =>
  catalog.data.value?.find((item) => item.id === selectedTypeId.value),
)
const fields = computed(
  () => detail.data.value?.definition_snapshot?.fields ?? selectedType.value?.fields ?? [],
)
const schemaAvailable = computed(
  () => !editing.value || !!selectedType.value || !!detail.data.value?.definition_snapshot?.fields,
)
const showSubmit = computed(() =>
  editing.value && detail.data.value
    ? canSubmitRequest(detail.data.value, auth.user?.id, can)
    : can('requests.submit'),
)
const formState = computed(() => ({ selectedTypeId: selectedTypeId.value, values: values.value }))
const { markClean, forceDiscard } = useDirtyState(formState)
watch(
  [detail.data, catalog.data],
  async ([value, catalogItems]) => {
    if (!editing.value || !value) return
    if (!canEditRequest(value, auth.user?.id, can)) {
      forceDiscard()
      selectedTypeId.value = null
      values.value = {}
      errors.value = {}
      generalError.value = ''
      void router.replace(`/requests/${value.id}`)
      return
    }
    selectedTypeId.value = value.request_type.id
    const schema =
      value.definition_snapshot?.fields ??
      catalogItems?.find((item) => item.id === value.request_type.id)?.fields
    if (!schema) {
      if (catalogItems) {
        hydrated.value = true
        generalError.value =
          'This draft request type is no longer available in the active request catalog, so its fields cannot be edited.'
      }
      return
    }
    values.value = initializeValues(schema, value.payload)
    hydrated.value = true
    await nextTick()
    markClean()
  },
  { immediate: true },
)
watch(selectedTypeId, () => {
  if (!editing.value) values.value = initializeValues(fields.value)
})
watch(workspaceId, (current, previous) => {
  if (previous && current !== previous && requestWorkspaceDestination(route.name)) {
    forceDiscard()
    selectedTypeId.value = null
    values.value = {}
    errors.value = {}
    generalError.value = ''
    void router.replace('/requests')
  }
})
const mutation = useMutation({
  mutationFn: (input: RequestPersistenceInput) => persistRequest(input, requestsApi),
  onSuccess: async (value, input) => {
    if (!canApplyRequestResult(input.workspaceId, workspaceId.value)) return
    markClean()
    await client.invalidateQueries({ queryKey: requestKeys.all(input.workspaceId) })
    toast.success(input.submit ? 'Request submitted' : 'Draft saved')
    forceDiscard()
    await router.replace(input.submit ? `/requests/${value.id}` : `/requests/${value.id}/edit`)
  },
  onError: async (error, input) => {
    if (!canApplyRequestResult(input.workspaceId, workspaceId.value)) return
    if (error instanceof PartialRequestSubmitError) {
      let authoritative = error.request
      try {
        authoritative = await requestsApi.detail(input.workspaceId, error.request.id)
      } catch {
        // The successful save response is still authoritative if the follow-up read fails.
      }
      if (!canApplyRequestResult(input.workspaceId, workspaceId.value)) return
      client.setQueryData(requestKeys.detail(input.workspaceId, authoritative.id), authoritative)
      await client.invalidateQueries({ queryKey: requestKeys.all(input.workspaceId) })
      selectedTypeId.value = authoritative.request_type.id
      const schema =
        authoritative.definition_snapshot?.fields ??
        catalog.data.value?.find((item) => item.id === authoritative.request_type.id)?.fields ??
        []
      values.value = initializeValues(schema, authoritative.payload)
      errors.value =
        error.submitError instanceof ApiError ? mapPayloadErrors(error.submitError.fieldErrors) : {}
      generalError.value = error.message
      forceDiscard()
      if (!editing.value || requestId.value !== authoritative.id)
        await router.replace(`/requests/${authoritative.id}/edit`)
      await nextTick()
      markClean()
      return
    }
    errors.value = error instanceof ApiError ? mapPayloadErrors(error.fieldErrors) : {}
    generalError.value = error instanceof Error ? error.message : 'Unable to save request.'
  },
})
function persist(submit = false) {
  errors.value = validateValues(fields.value, values.value, submit)
  generalError.value = ''
  if (Object.keys(errors.value).length) return
  if (
    submit &&
    !window.confirm(
      'Submit this request? Once submitted, it enters the approval workflow and can no longer be edited.',
    )
  )
    return
  mutation.mutate({
    workspaceId: workspaceId.value,
    requestId: editing.value ? requestId.value : 0,
    requestTypeId: selectedTypeId.value ?? 0,
    payload: serializeValues(fields.value, values.value),
    submit,
  })
}
</script>
<template>
  <AppShell
    ><PageHeader
      :title="editing ? 'Edit request draft' : 'Create request'"
      description="Save a draft or submit it to its configured approval workflow." /><LoadingState
      v-if="
        editing
          ? !hydrated || detail.isPending.value || catalog.isPending.value
          : catalog.isPending.value
      "
      label="Loading request form" />
    <div v-else class="mx-auto max-w-3xl space-y-6">
      <p
        v-if="generalError"
        class="rounded-md border border-destructive/40 p-4 text-sm text-destructive"
      >
        {{ generalError }}
      </p>
      <div v-if="!editing" class="space-y-2">
        <label for="request-type" class="text-sm font-medium">Request type</label
        ><select
          id="request-type"
          v-model.number="selectedTypeId"
          class="h-10 w-full rounded-md border bg-background px-3"
        >
          <option :value="null">Choose a request type</option>
          <option v-for="type in catalog.data.value ?? []" :key="type.id" :value="type.id">
            {{ type.name }}
          </option>
        </select>
        <p v-if="selectedType?.description" class="text-sm text-muted-foreground">
          {{ selectedType.description }}
        </p>
      </div>
      <RequestForm
        v-if="selectedTypeId && schemaAvailable"
        :fields="fields"
        :values="values"
        :errors="errors"
        :saving="mutation.isPending.value && !mutation.variables.value?.submit"
        :submitting="mutation.isPending.value && mutation.variables.value?.submit"
        :can-submit="showSubmit"
        @update:values="values = $event"
        @save="persist(false)"
        @submit="persist(true)"
      /></div
  ></AppShell>
</template>

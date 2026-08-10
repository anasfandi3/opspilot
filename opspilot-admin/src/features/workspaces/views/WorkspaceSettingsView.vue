<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import FormField from '@/components/app/forms/FormField.vue'
import LoadingState from '@/components/app/feedback/LoadingState.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ApiError } from '@/lib/api/errors'
import { useAuthorization } from '@/composables/useAuthorization'
import { useWorkspaceStore } from '@/stores/workspace'
import { workspaceApi } from '../api/workspaces'
import { workspaceKeys } from '../queries/workspaceKeys'
import {
  canApplyWorkspaceResult,
  workspaceUpdateInput,
  type WorkspaceUpdateInput,
} from '../workspaceBehavior'

const store = useWorkspaceStore()
const queryClient = useQueryClient()
const { can } = useAuthorization()
const workspaceId = computed(() => store.currentWorkspaceId ?? 0)
const query = useQuery({
  queryKey: computed(() => workspaceKeys.detail(workspaceId.value)),
  queryFn: () => workspaceApi.detail(workspaceId.value),
  enabled: computed(() => workspaceId.value > 0),
})
const form = reactive({ name: '', error: '', general: '' })
watch(
  () => query.data.value,
  (workspace) => {
    if (workspace) form.name = workspace.name
  },
  { immediate: true },
)
const mutation = useMutation({
  mutationFn: (input: WorkspaceUpdateInput) =>
    workspaceApi.update(input.workspaceId, { name: input.name }),
  onSuccess: async (workspace, input) => {
    if (!canApplyWorkspaceResult(input.workspaceId, workspaceId.value)) return
    store.updateWorkspace(workspace)
    queryClient.setQueryData(workspaceKeys.detail(workspace.id), workspace)
    await queryClient.invalidateQueries({ queryKey: workspaceKeys.detail(workspace.id) })
    toast.success('Workspace updated')
  },
  onError: (error, input) => {
    if (!canApplyWorkspaceResult(input.workspaceId, workspaceId.value)) return
    form.error = error instanceof ApiError ? (error.fieldErrors.name?.[0] ?? '') : ''
    form.general = error instanceof Error ? error.message : 'Unable to update workspace.'
  },
})
function save() {
  form.error = ''
  form.general = ''
  if (!form.name.trim()) {
    form.error = 'Workspace name is required.'
    return
  }
  mutation.mutate(workspaceUpdateInput(workspaceId.value, form.name.trim()))
}
</script>
<template>
  <AppShell
    ><PageHeader title="Workspace settings" description="View and manage the active workspace." />
    <LoadingState v-if="query.isPending.value" label="Loading workspace" />
    <div v-else-if="query.isError.value" class="rounded-lg border p-6">
      <p class="text-sm text-destructive">{{ query.error.value?.message }}</p>
      <Button class="mt-4" variant="outline" @click="query.refetch()">Retry</Button>
    </div>
    <section v-else-if="query.data.value" class="max-w-2xl rounded-lg border bg-card p-6">
      <form v-if="can('workspace.update')" class="space-y-5" @submit.prevent="save">
        <FormField label="Workspace name" :error="form.error" v-slot="slot"
          ><Input
            :id="slot.id"
            v-model="form.name"
            maxlength="255"
            :aria-invalid="slot.invalid"
            :aria-describedby="slot.describedby"
        /></FormField>
        <p v-if="form.general && !form.error" class="text-sm text-destructive">
          {{ form.general }}
        </p>
        <div class="text-sm text-muted-foreground">Slug: {{ query.data.value.slug }}</div>
        <Button type="submit" :disabled="mutation.isPending.value">{{
          mutation.isPending.value ? 'Saving…' : 'Save changes'
        }}</Button>
      </form>
      <dl v-else class="grid gap-4 text-sm">
        <div>
          <dt class="text-muted-foreground">Name</dt>
          <dd class="mt-1 font-medium">{{ query.data.value.name }}</dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Slug</dt>
          <dd class="mt-1 font-medium">{{ query.data.value.slug }}</dd>
        </div>
      </dl>
    </section>
  </AppShell>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import LoadingState from '@/components/app/feedback/LoadingState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ApiError } from '@/lib/api/errors'
import { useAuthorization } from '@/composables/useAuthorization'
import { useWorkspaceStore } from '@/stores/workspace'
import DynamicFieldPreview from '../components/DynamicFieldPreview.vue'
import { fieldTypeLabels } from '../fieldTypes'
import { requestTypesApi } from '../api/requestTypes'
import { requestTypeKeys } from '../queries/requestTypeKeys'
import type { FieldConfig, SelectOption } from '../types/requestType'
import { requestTypeWorkspaceSwitchDestination } from '../workspaceBehavior'

const route = useRoute()
const router = useRouter()
const workspace = useWorkspaceStore()
const { can } = useAuthorization()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const requestTypeId = computed(() => Number(route.params.id) || 0)
const query = useQuery({
  queryKey: computed(() => requestTypeKeys.detail(workspaceId.value, requestTypeId.value)),
  queryFn: () => requestTypesApi.detail(workspaceId.value, requestTypeId.value),
  enabled: computed(() => workspaceId.value > 0 && requestTypeId.value > 0),
})
function fieldOptions(config: FieldConfig): SelectOption[] {
  return (config as { options?: SelectOption[] } | null)?.options ?? []
}
watch(workspaceId, (current, previous) => {
  const destination = requestTypeWorkspaceSwitchDestination(route.name)
  if (previous && current !== previous && destination) router.replace(destination)
})
watch(
  () => query.error.value,
  (error) => error instanceof ApiError && error.kind === 'not-found' && router.replace('/404'),
)
</script>
<template>
  <AppShell
    ><LoadingState v-if="query.isPending.value" label="Loading request type" />
    <div v-else-if="query.isError.value" class="rounded-lg border p-6">
      <p class="text-sm text-destructive">{{ query.error.value?.message }}</p>
      <Button class="mt-4" variant="outline" @click="query.refetch()">Retry</Button>
    </div>
    <template v-else-if="query.data.value"
      ><PageHeader
        :title="query.data.value.name"
        :description="query.data.value.description || 'No description provided.'"
        ><template #actions
          ><Button variant="outline" as-child
            ><RouterLink to="/request-types">Back to list</RouterLink></Button
          ><Button v-if="can('request_types.manage')" as-child
            ><RouterLink :to="`/request-types/${query.data.value.id}/edit`"
              >Edit request type</RouterLink
            ></Button
          ></template
        ></PageHeader
      >
      <dl
        class="mb-8 grid gap-4 rounded-lg border bg-card p-5 text-sm sm:grid-cols-2 lg:grid-cols-4"
      >
        <div>
          <dt class="text-muted-foreground">Status</dt>
          <dd class="mt-1">
            <Badge :variant="query.data.value.is_active ? 'default' : 'secondary'">{{
              query.data.value.is_active ? 'Active' : 'Inactive'
            }}</Badge>
          </dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Slug</dt>
          <dd class="mt-1 font-medium">{{ query.data.value.slug }}</dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Created by</dt>
          <dd class="mt-1 font-medium">{{ query.data.value.creator.name }}</dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Fields</dt>
          <dd class="mt-1 font-medium">{{ query.data.value.fields.length }}</dd>
        </div>
      </dl>
      <section class="mb-8 space-y-4">
        <h2 class="text-xl font-semibold">Configured schema</h2>
        <p
          v-if="!query.data.value.fields.length"
          class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
          This request type has no fields.
        </p>
        <Card v-for="field in query.data.value.fields" :key="field.id"
          ><CardHeader class="flex-row items-center justify-between"
            ><CardTitle class="text-base">{{ field.position }}. {{ field.label }}</CardTitle
            ><Badge variant="outline">{{
              field.is_required ? 'Required' : 'Optional'
            }}</Badge></CardHeader
          ><CardContent class="space-y-2 text-sm"
            ><p><span class="text-muted-foreground">Key:</span> {{ field.key }}</p>
            <p>
              <span class="text-muted-foreground">Type:</span> {{ fieldTypeLabels[field.type] }}
            </p>
            <p v-if="field.description">{{ field.description }}</p>
            <div v-if="field.config" class="rounded-md bg-muted/50 p-3">
              <template v-if="field.type === 'select' || field.type === 'multiselect'"
                ><p class="mb-2 font-medium">Options</p>
                <ul class="list-inside list-disc">
                  <li v-for="option in fieldOptions(field.config)" :key="option.value">
                    {{ option.label }}
                    <span class="text-muted-foreground">({{ option.value }})</span>
                  </li>
                </ul></template
              >
              <pre v-else class="whitespace-pre-wrap text-xs">{{ field.config }}</pre>
            </div></CardContent
          ></Card
        >
      </section>
      <DynamicFieldPreview :fields="query.data.value.fields" />
    </template>
  </AppShell>
</template>

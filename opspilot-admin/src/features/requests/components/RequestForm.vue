<script setup lang="ts">
import type { RequestTypeField } from '@/features/request-types/types/requestType'
import type { RequestPayload } from '../types/request'
import DynamicRequestField from './DynamicRequestField.vue'
defineProps<{
  fields: RequestTypeField[]
  values: RequestPayload
  errors: Record<string, string[]>
  saving?: boolean
  submitting?: boolean
  canSubmit?: boolean
}>()
const emit = defineEmits<{ 'update:values': [value: RequestPayload]; save: []; submit: [] }>()
function update(values: RequestPayload, key: string, value: RequestPayload[string]) {
  emit('update:values', { ...values, [key]: value })
}
</script>
<template>
  <form class="space-y-6" @submit.prevent="emit('save')">
    <DynamicRequestField
      v-for="field in fields"
      :key="field.id"
      :field="field"
      :model-value="values[field.key] ?? null"
      :error="errors[field.key]?.[0]"
      @update:model-value="(value) => update(values, field.key, value)"
    />
    <div class="flex flex-wrap gap-3 border-t pt-5">
      <button
        class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50"
        :disabled="saving || submitting"
      >
        {{ saving ? 'Saving…' : 'Save draft' }}</button
      ><button
        v-if="canSubmit"
        type="button"
        class="rounded-md border px-4 py-2 text-sm disabled:opacity-50"
        :disabled="saving || submitting"
        @click="emit('submit')"
      >
        {{ submitting ? 'Submitting…' : 'Submit request' }}
      </button>
    </div>
  </form>
</template>

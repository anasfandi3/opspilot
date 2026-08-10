<script setup lang="ts">
import { Plus } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import type { DraftField } from '../types/requestType'
import { createDraftField, moveField } from '../fieldTypes'
import DynamicFieldCard from './DynamicFieldCard.vue'

const props = defineProps<{ modelValue: DraftField[]; errors: Record<string, string[]> }>()
const emit = defineEmits<{
  'update:modelValue': [fields: DraftField[]]
  structural: []
}>()
function update(index: number, field: DraftField) {
  const fields = [...props.modelValue]
  fields[index] = field
  emit('update:modelValue', fields)
}
function structure(mutator: (fields: DraftField[]) => void) {
  const fields = [...props.modelValue]
  mutator(fields)
  emit('update:modelValue', fields)
  emit('structural')
}
</script>
<template>
  <section class="space-y-4" aria-labelledby="dynamic-fields-heading">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 id="dynamic-fields-heading" class="text-xl font-semibold">Dynamic fields</h2>
        <p class="mt-1 text-sm text-muted-foreground">
          Define the ordered fields shown by this request type.
        </p>
      </div>
      <Button
        type="button"
        variant="outline"
        @click="structure((fields) => fields.push(createDraftField()))"
        ><Plus class="size-4" />Add field</Button
      >
    </div>
    <p
      v-if="!modelValue.length"
      class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
    >
      No fields configured yet.
    </p>
    <DynamicFieldCard
      v-for="(field, index) in modelValue"
      :key="field.clientId"
      :field="field"
      :index="index"
      :total="modelValue.length"
      :errors="errors"
      @update="update(index, $event)"
      @structural="emit('structural')"
      @up="structure((fields) => moveField(fields, index, index - 1))"
      @down="structure((fields) => moveField(fields, index, index + 1))"
      @remove="structure((fields) => fields.splice(index, 1))"
    />
  </section>
</template>

<script setup lang="ts">
import FormField from '@/components/app/forms/FormField.vue'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { fieldTypeLabels } from '../fieldTypes'
import type { DraftField, RequestTypeField } from '../types/requestType'
defineProps<{ fields: Array<DraftField | RequestTypeField> }>()
</script>
<template>
  <section class="rounded-lg border bg-muted/20 p-5" aria-labelledby="field-preview-heading">
    <h2 id="field-preview-heading" class="text-lg font-semibold">Form preview</h2>
    <p class="mt-1 text-sm text-muted-foreground">
      Read-only preview; it does not submit a request.
    </p>
    <div class="mt-5 grid gap-5 md:grid-cols-2">
      <div
        v-for="field in fields"
        :key="'clientId' in field ? field.clientId : field.id"
        :class="field.type === 'textarea' && 'md:col-span-2'"
      >
        <FormField
          :label="field.label || 'Untitled field'"
          :required="field.is_required"
          v-slot="slot"
        >
          <Textarea
            v-if="field.type === 'textarea'"
            :id="slot.id"
            disabled
            placeholder="Long text response"
          />
          <div v-else-if="field.type === 'boolean'" class="flex h-9 items-center gap-2">
            <Checkbox :id="slot.id" disabled /><span class="text-sm">Yes</span>
          </div>
          <select
            v-else-if="field.type === 'select' || field.type === 'multiselect'"
            :id="slot.id"
            disabled
            class="h-9 w-full rounded-md border bg-background px-3 text-sm"
          >
            <option>{{ fieldTypeLabels[field.type] }}</option>
          </select>
          <Input
            v-else
            :id="slot.id"
            :type="field.type === 'datetime' ? 'datetime-local' : field.type"
            disabled
            :placeholder="fieldTypeLabels[field.type]"
          />
        </FormField>
        <p v-if="field.description" class="mt-1 text-xs text-muted-foreground">
          {{ field.description }}
        </p>
      </div>
    </div>
  </section>
</template>

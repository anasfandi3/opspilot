<script setup lang="ts">
import { ArrowDown, ArrowUp, Trash2 } from '@lucide/vue'
import FormField from '@/components/app/forms/FormField.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { fieldTypeLabels, normalizeFieldType, validateOptions } from '../fieldTypes'
import {
  requestFieldTypes,
  type DraftField,
  type RequestFieldType,
  type SelectOption,
} from '../types/requestType'
import SelectOptionsEditor from './SelectOptionsEditor.vue'

const props = defineProps<{
  field: DraftField
  index: number
  total: number
  errors: Record<string, string[]>
}>()
const emit = defineEmits<{
  update: [field: DraftField]
  up: []
  down: []
  remove: []
  structural: []
}>()
function patch(value: Partial<DraftField>) {
  emit('update', { ...props.field, ...value })
}
function changeType(type: RequestFieldType) {
  emit('update', normalizeFieldType(props.field, type))
  emit('structural')
}
function configValue(key: string): number | undefined {
  const config = props.field.config as Record<string, number> | null
  return config?.[key]
}
function setConfigNumber(key: string, value: string | number) {
  const next = Object.assign({}, props.field.config as Record<string, number> | null)
  if (value === '') delete next[key]
  else next[key] = Number(value)
  patch({ config: Object.keys(next).length ? next : null })
}
function options() {
  return (props.field.config as { options?: SelectOption[] } | null)?.options ?? []
}
function updateOptions(value: SelectOption[]) {
  patch({ config: { options: value } })
}
function error(path: string) {
  return props.errors[`fields.${props.index}.${path}`]?.[0] ?? ''
}
</script>
<template>
  <article
    class="rounded-lg border bg-card p-4 sm:p-5"
    :aria-labelledby="`field-${field.clientId}`"
  >
    <div class="mb-5 flex flex-wrap items-center gap-2">
      <h3 :id="`field-${field.clientId}`" class="mr-auto font-semibold">
        Field {{ index + 1 }}<span v-if="field.label"> · {{ field.label }}</span>
      </h3>
      <Button
        type="button"
        size="icon"
        variant="outline"
        :disabled="index === 0"
        :aria-label="`Move ${field.label || `field ${index + 1}`} up`"
        @click="emit('up')"
        ><ArrowUp class="size-4"
      /></Button>
      <Button
        type="button"
        size="icon"
        variant="outline"
        :disabled="index === total - 1"
        :aria-label="`Move ${field.label || `field ${index + 1}`} down`"
        @click="emit('down')"
        ><ArrowDown class="size-4"
      /></Button>
      <Button
        type="button"
        size="icon"
        variant="ghost"
        :aria-label="`Remove ${field.label || `field ${index + 1}`}`"
        @click="emit('remove')"
        ><Trash2 class="size-4"
      /></Button>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
      <FormField label="Label" required :error="error('label')" v-slot="slot"
        ><Input
          :id="slot.id"
          :model-value="field.label"
          maxlength="255"
          :aria-invalid="slot.invalid"
          :aria-describedby="slot.describedby"
          @update:model-value="patch({ label: String($event) })"
      /></FormField>
      <FormField label="Key" required :error="error('key')" v-slot="slot"
        ><Input
          :id="slot.id"
          :model-value="field.key"
          maxlength="255"
          placeholder="cost_center"
          :disabled="Boolean(field.id)"
          :aria-invalid="slot.invalid"
          :aria-describedby="slot.describedby"
          @update:model-value="patch({ key: String($event) })"
      /></FormField>
      <FormField label="Field type" required :error="error('type')" v-slot="slot"
        ><Select
          :model-value="field.type"
          :disabled="Boolean(field.id)"
          @update:model-value="(value) => changeType(value as RequestFieldType)"
          ><SelectTrigger :id="slot.id" class="w-full"><SelectValue /></SelectTrigger
          ><SelectContent
            ><SelectItem v-for="type in requestFieldTypes" :key="type" :value="type">{{
              fieldTypeLabels[type]
            }}</SelectItem></SelectContent
          ></Select
        ></FormField
      >
      <div class="flex items-center gap-3 pt-7">
        <Switch
          :id="`required-${field.clientId}`"
          :model-value="field.is_required"
          @update:model-value="patch({ is_required: $event })"
        /><Label :for="`required-${field.clientId}`">Required field</Label>
      </div>
      <FormField
        class="md:col-span-2"
        label="Description"
        :error="error('description')"
        v-slot="slot"
        ><Textarea
          :id="slot.id"
          :model-value="field.description"
          maxlength="5000"
          :aria-invalid="slot.invalid"
          :aria-describedby="slot.describedby"
          @update:model-value="patch({ description: String($event) })"
      /></FormField>
    </div>
    <div v-if="['text', 'textarea'].includes(field.type)" class="mt-4 grid gap-4 sm:grid-cols-2">
      <FormField label="Minimum length" v-slot="slot"
        ><Input
          :id="slot.id"
          type="number"
          min="0"
          :model-value="configValue('min_length')"
          @update:model-value="setConfigNumber('min_length', $event)" /></FormField
      ><FormField label="Maximum length" v-slot="slot"
        ><Input
          :id="slot.id"
          type="number"
          min="1"
          :model-value="configValue('max_length')"
          @update:model-value="setConfigNumber('max_length', $event)"
      /></FormField>
    </div>
    <div v-else-if="['email', 'url'].includes(field.type)" class="mt-4">
      <FormField label="Maximum length" v-slot="slot"
        ><Input
          :id="slot.id"
          type="number"
          min="1"
          :model-value="configValue('max_length')"
          @update:model-value="setConfigNumber('max_length', $event)"
      /></FormField>
    </div>
    <div
      v-else-if="['number', 'decimal'].includes(field.type)"
      class="mt-4 grid gap-4 sm:grid-cols-2"
    >
      <FormField label="Minimum" v-slot="slot"
        ><Input
          :id="slot.id"
          type="number"
          :step="field.type === 'decimal' ? 'any' : '1'"
          :model-value="configValue('min')"
          @update:model-value="setConfigNumber('min', $event)" /></FormField
      ><FormField label="Maximum" v-slot="slot"
        ><Input
          :id="slot.id"
          type="number"
          :step="field.type === 'decimal' ? 'any' : '1'"
          :model-value="configValue('max')"
          @update:model-value="setConfigNumber('max', $event)"
      /></FormField>
    </div>
    <div v-else-if="field.type === 'select' || field.type === 'multiselect'" class="mt-5">
      <h4 class="mb-3 text-sm font-medium">Options</h4>
      <SelectOptionsEditor
        :model-value="options()"
        :error="error('config') || validateOptions(options())"
        @update:model-value="updateOptions"
      />
    </div>
    <p
      v-if="error('config') && field.type !== 'select' && field.type !== 'multiselect'"
      class="mt-3 text-sm text-destructive"
    >
      {{ error('config') }}
    </p>
  </article>
</template>

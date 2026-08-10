<script setup lang="ts">
import { computed } from 'vue'
import type { RequestTypeField, SelectOption } from '@/features/request-types/types/requestType'
import type { RequestValue } from '../types/request'
import { datetimeEditorValue } from '@/features/workflows/conditions'
import { updateDatetime } from '../fieldValues'
import { lengthConfig, numericInputValue, rangeConfig } from '../fieldConfig'
const props = defineProps<{ field: RequestTypeField; modelValue: RequestValue; error?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: RequestValue] }>()
const options = computed(
  () => (props.field.config as { options?: SelectOption[] } | null)?.options ?? [],
)
const id = computed(() => `request-field-${props.field.id}`)
function input(value: string) {
  emit('update:modelValue', value || null)
}
function numeric(value: string) {
  emit('update:modelValue', numericInputValue(value, props.field.type))
}
function toggleOption(value: string, checked: boolean) {
  const current = Array.isArray(props.modelValue) ? props.modelValue : []
  emit(
    'update:modelValue',
    checked ? [...current, value] : current.filter((item) => item !== value),
  )
}
</script>
<template>
  <div class="space-y-2">
    <label :for="id" class="text-sm font-medium"
      >{{ field.label }}
      <span v-if="field.is_required" aria-hidden="true" class="text-destructive">*</span></label
    >
    <p v-if="field.description" :id="`${id}-description`" class="text-sm text-muted-foreground">
      {{ field.description }}
    </p>
    <textarea
      v-if="field.type === 'textarea'"
      :id="id"
      class="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
      :value="String(modelValue ?? '')"
      :aria-invalid="!!error"
      @input="input(($event.target as HTMLTextAreaElement).value)"
    />
    <input
      v-else-if="['text', 'email', 'url', 'date'].includes(field.type)"
      :id="id"
      :type="field.type === 'text' ? 'text' : field.type"
      class="h-9 w-full rounded-md border bg-background px-3 text-sm"
      :value="String(modelValue ?? '')"
      :minlength="lengthConfig(field.config).min_length"
      :maxlength="lengthConfig(field.config).max_length"
      :aria-invalid="!!error"
      @input="input(($event.target as HTMLInputElement).value)"
    />
    <input
      v-else-if="field.type === 'number' || field.type === 'decimal'"
      :id="id"
      type="number"
      :step="field.type === 'number' ? '1' : 'any'"
      :min="rangeConfig(field.config).min"
      :max="rangeConfig(field.config).max"
      class="h-9 w-full rounded-md border bg-background px-3 text-sm"
      :value="modelValue ?? ''"
      :aria-invalid="!!error"
      @input="numeric(($event.target as HTMLInputElement).value)"
    />
    <input
      v-else-if="field.type === 'datetime'"
      :id="id"
      type="datetime-local"
      class="h-9 w-full rounded-md border bg-background px-3 text-sm"
      :value="datetimeEditorValue(modelValue)"
      :aria-invalid="!!error"
      @input="
        emit(
          'update:modelValue',
          updateDatetime(($event.target as HTMLInputElement).value, modelValue),
        )
      "
    />
    <label v-else-if="field.type === 'boolean'" class="flex items-center gap-2 text-sm"
      ><input
        :id="id"
        type="checkbox"
        :checked="modelValue === true"
        @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
      />
      Yes</label
    >
    <select
      v-else-if="field.type === 'select'"
      :id="id"
      class="h-9 w-full rounded-md border bg-background px-3 text-sm"
      :value="modelValue ?? ''"
      @change="input(($event.target as HTMLSelectElement).value)"
    >
      <option value="">Select an option</option>
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
    <fieldset v-else-if="field.type === 'multiselect'" class="space-y-2 rounded-md border p-3">
      <legend class="sr-only">{{ field.label }}</legend>
      <label v-for="option in options" :key="option.value" class="flex items-center gap-2 text-sm"
        ><input
          type="checkbox"
          :checked="Array.isArray(modelValue) && modelValue.includes(option.value)"
          @change="toggleOption(option.value, ($event.target as HTMLInputElement).checked)"
        />{{ option.label }}</label
      >
    </fieldset>
    <p v-if="error" :id="`${id}-error`" class="text-sm text-destructive">{{ error }}</p>
  </div>
</template>

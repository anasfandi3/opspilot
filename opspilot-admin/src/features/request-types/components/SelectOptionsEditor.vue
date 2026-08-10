<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { SelectOption } from '../types/requestType'
import { addOption, removeOption, updateOption } from '../fieldTypes'

const props = defineProps<{ modelValue: SelectOption[]; error?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: SelectOption[]] }>()
function update(index: number, key: keyof SelectOption, value: string) {
  emit('update:modelValue', updateOption(props.modelValue, index, key, value))
}
function remove(index: number) {
  emit('update:modelValue', removeOption(props.modelValue, index))
}
</script>
<template>
  <div class="space-y-3">
    <div
      v-for="(option, index) in modelValue"
      :key="index"
      class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
    >
      <Input
        :model-value="option.value"
        :aria-label="`Option ${index + 1} value`"
        placeholder="value"
        @update:model-value="update(index, 'value', String($event))"
      />
      <Input
        :model-value="option.label"
        :aria-label="`Option ${index + 1} label`"
        placeholder="Display label"
        @update:model-value="update(index, 'label', String($event))"
      />
      <Button
        type="button"
        variant="ghost"
        size="icon"
        :aria-label="`Remove option ${index + 1}`"
        @click="remove(index)"
        ><Trash2 class="size-4"
      /></Button>
    </div>
    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    <Button
      type="button"
      variant="outline"
      size="sm"
      @click="emit('update:modelValue', addOption(modelValue))"
      ><Plus class="size-4" />Add option</Button
    >
  </div>
</template>

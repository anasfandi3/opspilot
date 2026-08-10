<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { addStringSetValue, removeStringSetValue, updateStringSetValue } from '../conditions'

defineProps<{ modelValue: string[]; label: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()
</script>
<template>
  <div class="mt-1 space-y-2">
    <div v-for="(value, index) in modelValue" :key="index" class="flex items-center gap-2">
      <Input
        :model-value="value"
        :aria-label="`${label} ${index + 1}`"
        @update:model-value="
          emit('update:modelValue', updateStringSetValue(modelValue, index, String($event)))
        "
      />
      <Button
        type="button"
        size="sm"
        variant="ghost"
        :aria-label="`Remove ${label.toLowerCase()} ${index + 1}`"
        @click="emit('update:modelValue', removeStringSetValue(modelValue, index))"
        >Remove</Button
      >
    </div>
    <Button
      type="button"
      size="sm"
      variant="outline"
      @click="emit('update:modelValue', addStringSetValue(modelValue))"
      >Add value</Button
    >
  </div>
</template>

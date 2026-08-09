<script setup lang="ts">
import { useId } from 'vue'
import { Label } from '@/components/ui/label'
const props = defineProps<{
  label: string
  id?: string
  required?: boolean
  description?: string
  error?: string
}>()
const fieldId = props.id ?? useId()
</script>
<template>
  <div class="space-y-2" :data-invalid="Boolean(error)">
    <Label :for="fieldId"
      >{{ label }} <span v-if="required" class="text-destructive" aria-hidden="true">*</span
      ><span v-if="required" class="sr-only">required</span></Label
    >
    <p v-if="description" :id="`${fieldId}-description`" class="text-xs text-muted-foreground">
      {{ description }}
    </p>
    <slot
      :id="fieldId"
      :describedby="
        [description && `${fieldId}-description`, error && `${fieldId}-error`]
          .filter(Boolean)
          .join(' ') || undefined
      "
      :invalid="Boolean(error)"
    />
    <p
      v-if="error"
      :id="`${fieldId}-error`"
      class="text-xs font-medium text-destructive"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import type { RequestTypeField } from '@/features/request-types/types/requestType'
import type { RequestPayload } from '../types/request'
import { formatValue } from '../fieldValues'
const props = defineProps<{ fields: RequestTypeField[]; payload: RequestPayload }>()
const entries = Object.entries(props.payload)
  .map(([key, value]) => ({ key, value, field: props.fields.find((item) => item.key === key) }))
  .sort((a, b) => (a.field?.position ?? 999) - (b.field?.position ?? 999))
</script>
<template>
  <dl class="grid gap-4 sm:grid-cols-2">
    <div v-for="entry in entries" :key="entry.key" class="rounded-md border p-4">
      <dt class="text-sm font-medium">{{ entry.field?.label ?? entry.key }}</dt>
      <dd class="mt-1 whitespace-pre-wrap break-words text-sm text-muted-foreground">
        {{ formatValue(entry.field, entry.value) }}
      </dd>
    </div>
  </dl>
</template>

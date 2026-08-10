<script setup lang="ts">
import { computed } from 'vue'
import { trendBarHeight } from '../reportFormatting'
const props = defineProps<{ rows: Array<{ label: string; value: number; detail?: string }> }>()
const maximum = computed(() => Math.max(1, ...props.rows.map((row) => row.value)))
</script>
<template>
  <div>
    <div class="flex h-32 items-end gap-px overflow-x-auto pt-4" aria-hidden="true">
      <div
        v-for="row in rows"
        :key="row.label"
        class="min-w-1 flex-1 rounded-t bg-primary/70"
        :style="{ height: `${trendBarHeight(row.value, maximum)}%` }"
        :title="`${row.label}: ${row.detail ?? row.value}`"
      />
    </div>
    <ul class="sr-only" aria-label="Daily trend values">
      <li v-for="row in rows" :key="row.label">{{ row.label }}: {{ row.detail ?? row.value }}</li>
    </ul>
  </div>
</template>

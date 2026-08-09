<script lang="ts">
export const formGridColumns = {
  1: 'grid-cols-1',
  2: 'grid-cols-1 md:grid-cols-2',
  3: 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3',
} as const
export type FormGridColumns = keyof typeof formGridColumns
</script>
<script setup lang="ts">
import { cn } from '@/lib/utils'
const props = withDefaults(defineProps<{ columns?: FormGridColumns; class?: string }>(), {
  columns: 2,
})
</script>
<template>
  <div :class="cn('grid gap-5', formGridColumns[props.columns], props.class)"><slot /></div>
</template>

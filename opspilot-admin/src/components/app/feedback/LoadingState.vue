<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton'
withDefaults(defineProps<{ rows?: number }>(), { rows: 4 })
</script>
<template>
  <div class="space-y-4" aria-label="Loading content" aria-busy="true">
    <div class="flex justify-between">
      <Skeleton class="h-8 w-48" /><Skeleton class="h-8 w-24" />
    </div>
    <Skeleton v-for="row in rows" :key="row" class="h-12 w-full" />
  </div>
</template>

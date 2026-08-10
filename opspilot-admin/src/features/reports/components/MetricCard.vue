<script setup lang="ts">
defineProps<{ label: string; value: string | number; note?: string }>()
</script>
<template>
  <div class="rounded-lg border bg-card p-5">
    <p class="text-sm text-muted-foreground">{{ label }}</p>
    <p class="mt-2 text-3xl font-semibold">{{ value }}</p>
    <p v-if="note" class="mt-1 text-xs text-muted-foreground">{{ note }}</p>
  </div>
</template>

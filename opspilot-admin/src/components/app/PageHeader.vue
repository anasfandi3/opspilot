<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>
<template>
  <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight md:text-3xl">{{ title }}</h1>
      <p v-if="description" class="mt-2 max-w-3xl text-sm text-muted-foreground">
        {{ description }}
      </p>
    </div>
    <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2"><slot name="actions" /></div>
  </div>
  <div v-if="$slots.tools" class="mb-6"><slot name="tools" /></div>
</template>

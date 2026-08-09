<script setup lang="ts">
import { Inbox } from '@lucide/vue'
withDefaults(defineProps<{ title: string; description: string; icon?: typeof Inbox }>(), {
  icon: () => Inbox,
})
</script>
<template>
  <div
    class="flex min-h-52 flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center"
  >
    <div class="mb-4 rounded-full bg-muted p-3">
      <component :is="icon" class="size-5 text-muted-foreground" />
    </div>
    <h3 class="font-semibold">{{ title }}</h3>
    <p class="mt-1 max-w-sm text-sm text-muted-foreground">{{ description }}</p>
    <div v-if="$slots.action" class="mt-5"><slot name="action" /></div>
  </div>
</template>

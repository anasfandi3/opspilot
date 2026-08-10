<script setup lang="ts">
import { Button } from '@/components/ui/button'

defineProps<{ page: number; lastPage: number }>()
defineEmits<{ change: [page: number] }>()
</script>

<template>
  <nav v-if="lastPage > 1" class="mt-4 flex items-center gap-3" aria-label="Section pages">
    <Button variant="outline" size="sm" :disabled="page <= 1" @click="$emit('change', page - 1)">
      Previous
    </Button>
    <span class="text-sm text-muted-foreground">Page {{ page }} of {{ lastPage }}</span>
    <Button
      variant="outline"
      size="sm"
      :disabled="page >= lastPage"
      @click="$emit('change', page + 1)"
    >
      Next
    </Button>
  </nav>
</template>

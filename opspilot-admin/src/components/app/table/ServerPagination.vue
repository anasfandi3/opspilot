<script setup lang="ts">
import { computed } from 'vue'
import { MoreHorizontal } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { createPaginationRange } from './paginationRange'

const props = defineProps<{ page: number; pageCount: number }>()
const emit = defineEmits<{ 'update:page': [page: number] }>()
const items = computed(() => createPaginationRange(props.page, props.pageCount))

function selectPage(page: number) {
  if (page !== props.page && page >= 1 && page <= props.pageCount) emit('update:page', page)
}
</script>

<template>
  <nav class="flex items-center gap-1" aria-label="Table pagination">
    <Button variant="outline" size="sm" :disabled="page <= 1" @click="selectPage(page - 1)"
      >Previous</Button
    >
    <template v-for="item in items" :key="item">
      <span
        v-if="typeof item !== 'number'"
        class="grid size-9 place-items-center text-muted-foreground"
        aria-hidden="true"
        ><MoreHorizontal class="size-4"
      /></span>
      <Button
        v-else
        :variant="item === page ? 'default' : 'outline'"
        size="icon-sm"
        :aria-label="`Go to page ${item}`"
        :aria-current="item === page ? 'page' : undefined"
        @click="selectPage(item)"
        >{{ item }}</Button
      >
    </template>
    <Button variant="outline" size="sm" :disabled="page >= pageCount" @click="selectPage(page + 1)"
      >Next</Button
    >
  </nav>
</template>

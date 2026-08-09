<script setup lang="ts">
import { ref } from 'vue'
import { Paperclip, X } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
defineProps<{ id?: string; accept?: string; disabled?: boolean }>()
const emit = defineEmits<{ change: [file: File | null] }>()
const input = ref<HTMLInputElement>()
const filename = ref('')
function changed(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  filename.value = file?.name ?? ''
  emit('change', file)
}
function clear() {
  filename.value = ''
  if (input.value) input.value.value = ''
  emit('change', null)
}
</script>
<template>
  <div class="space-y-2">
    <Input
      :id="id"
      ref="input"
      type="file"
      :accept="accept"
      :disabled="disabled"
      class="file:mr-3 file:font-medium"
      @change="changed"
    />
    <div
      v-if="filename"
      class="flex items-center justify-between rounded-md bg-muted px-3 py-2 text-sm"
    >
      <span class="flex min-w-0 items-center gap-2"
        ><Paperclip class="size-4 shrink-0" /><span class="truncate">{{ filename }}</span></span
      ><Button
        variant="ghost"
        size="icon"
        class="size-7"
        :disabled="disabled"
        aria-label="Clear selected file"
        @click="clear"
        ><X class="size-4"
      /></Button>
    </div>
  </div>
</template>

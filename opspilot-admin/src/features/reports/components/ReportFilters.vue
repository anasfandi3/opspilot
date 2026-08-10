<script setup lang="ts">
import { ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { validateReportRange } from '../reportFilters'
const props = defineProps<{ from?: string; to?: string }>()
const emit = defineEmits<{ apply: [from: string, to: string]; reset: [] }>()
const from = ref(props.from ?? ''),
  to = ref(props.to ?? ''),
  error = ref('')
watch(
  () => [props.from, props.to],
  () => {
    from.value = props.from ?? ''
    to.value = props.to ?? ''
    error.value = ''
  },
)
function apply() {
  error.value = validateReportRange(from.value, to.value)
  if (!error.value) emit('apply', from.value, to.value)
}
</script>
<template>
  <div class="rounded-lg border bg-card p-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
      <label class="grid gap-1 text-sm font-medium"
        >From (UTC)<Input v-model="from" type="date" /></label
      ><label class="grid gap-1 text-sm font-medium"
        >To (UTC)<Input v-model="to" type="date"
      /></label>
      <div class="flex gap-2">
        <Button @click="apply">Apply</Button
        ><Button variant="outline" @click="$emit('reset')">Reset</Button>
      </div>
    </div>
    <p v-if="error" class="mt-2 text-sm text-destructive">{{ error }}</p>
  </div>
</template>

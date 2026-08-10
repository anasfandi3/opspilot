<script setup lang="ts">
import { Spinner } from '@/components/ui/spinner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
defineProps<{
  open: boolean
  decision: 'approve' | 'reject'
  requestId: number
  stepName: string
  loading?: boolean
  error?: string
}>()
const emit = defineEmits<{ confirm: []; 'update:open': [value: boolean] }>()
</script>
<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)"
    ><DialogContent
      ><DialogHeader
        ><DialogTitle>{{
          decision === 'approve' ? 'Approve request?' : 'Reject request?'
        }}</DialogTitle
        ><DialogDescription>{{
          decision === 'approve'
            ? `Approve request #${requestId} at ${stepName}? The backend will advance the authoritative approval sequence.`
            : `Reject request #${requestId} at ${stepName}? The request and remaining open approvals will become terminal.`
        }}</DialogDescription></DialogHeader
      >
      <p v-if="error" class="rounded-md border border-destructive/40 p-3 text-sm text-destructive">
        {{ error }}
      </p>
      <DialogFooter
        ><Button variant="outline" :disabled="loading" @click="emit('update:open', false)"
          >Cancel</Button
        ><Button
          :variant="decision === 'reject' ? 'destructive' : 'default'"
          :disabled="loading"
          @click="emit('confirm')"
          ><Spinner v-if="loading" />{{ decision === 'approve' ? 'Approve' : 'Reject' }}</Button
        ></DialogFooter
      ></DialogContent
    ></Dialog
  >
</template>

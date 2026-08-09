<script setup lang="ts">
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Spinner } from '@/components/ui/spinner'
withDefaults(
  defineProps<{
    title: string
    description: string
    confirmText?: string
    cancelText?: string
    destructive?: boolean
    loading?: boolean
  }>(),
  { confirmText: 'Confirm', cancelText: 'Cancel' },
)
defineEmits<{ confirm: [] }>()
</script>
<template>
  <AlertDialog
    ><AlertDialogTrigger as-child><slot name="trigger" /></AlertDialogTrigger
    ><AlertDialogContent
      ><AlertDialogHeader
        ><AlertDialogTitle>{{ title }}</AlertDialogTitle
        ><AlertDialogDescription>{{ description }}</AlertDialogDescription></AlertDialogHeader
      ><AlertDialogFooter
        ><AlertDialogCancel :disabled="loading">{{ cancelText }}</AlertDialogCancel
        ><AlertDialogAction
          :disabled="loading"
          :class="destructive && 'bg-destructive text-white hover:bg-destructive/90'"
          @click.prevent="$emit('confirm')"
          ><Spinner v-if="loading" />{{ confirmText }}</AlertDialogAction
        ></AlertDialogFooter
      ></AlertDialogContent
    ></AlertDialog
  >
</template>

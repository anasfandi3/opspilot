<script setup lang="ts">
import { Button } from '@/components/ui/button'
import type { OpsNotification } from '../types/notification'
import { isUnread, notificationBody, notificationTitle } from '../notificationPresentation'

defineProps<{ notification: OpsNotification; busy?: boolean }>()
defineEmits<{ open: []; toggle: [] }>()
</script>

<template>
  <article class="flex gap-3 border-b p-4 last:border-b-0">
    <span
      class="mt-2 size-2 shrink-0 rounded-full"
      :class="isUnread(notification) ? 'bg-primary' : 'bg-transparent'"
      :aria-label="isUnread(notification) ? 'Unread notification' : 'Read notification'"
    />
    <div class="min-w-0 flex-1">
      <button class="w-full text-left" @click="$emit('open')">
        <strong class="text-sm">{{ notificationTitle(notification) }}</strong>
        <p class="mt-1 break-words text-sm text-muted-foreground">
          {{ notificationBody(notification) }}
        </p>
        <time class="mt-1 block text-xs text-muted-foreground">
          {{ new Date(notification.created_at).toLocaleString() }}
        </time>
      </button>
      <Button
        variant="link"
        size="sm"
        class="mt-1 h-auto px-0"
        :disabled="busy"
        @click="$emit('toggle')"
      >
        Mark as {{ isUnread(notification) ? 'read' : 'unread' }}
      </Button>
    </div>
  </article>
</template>

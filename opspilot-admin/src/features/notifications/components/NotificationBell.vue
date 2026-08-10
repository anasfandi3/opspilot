<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Bell, CheckCheck } from '@lucide/vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkspaceStore } from '@/stores/workspace'
import { useNotificationStore } from '@/stores/notifications'
import { useAuthStore } from '@/stores/auth'
import { notificationApi } from '../api/notifications'
import { notificationKeys } from '../queries/notificationKeys'
import { canApplyNotificationResult, isUnread, unreadBadge } from '../notificationPresentation'
import { notificationNavigationPlan } from '../notificationNavigation'
import type { OpsNotification } from '../types/notification'
import NotificationItem from './NotificationItem.vue'

const workspace = useWorkspaceStore(),
  summary = useNotificationStore(),
  auth = useAuthStore(),
  client = useQueryClient(),
  router = useRouter(),
  open = ref(false),
  actionError = ref('')
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const count = useQuery({
  queryKey: notificationKeys.unreadCount(),
  queryFn: notificationApi.unreadCount,
  enabled: () => workspaceId.value > 0,
})
watch(
  () => count.data.value,
  (value) => {
    if (value !== undefined) summary.synchronizeUnreadCount(value)
  },
  { immediate: true },
)
const recent = useQuery({
  queryKey: notificationKeys.recent(),
  queryFn: () => notificationApi.list(1, 5),
  enabled: () => open.value && workspaceId.value > 0,
})
watch(workspaceId, () => {
  open.value = false
  actionError.value = ''
})
async function refresh(sourceUserId: number) {
  if (!canApplyNotificationResult(sourceUserId, auth.user?.id)) return
  await Promise.all([
    client.invalidateQueries({ queryKey: notificationKeys.all() }),
    client.invalidateQueries({ queryKey: notificationKeys.unreadCount() }),
  ])
}
const toggle = useMutation({
  mutationFn: (input: { userId: number; notification: OpsNotification }) =>
    isUnread(input.notification)
      ? notificationApi.markRead(input.notification.id)
      : notificationApi.markUnread(input.notification.id),
  onSuccess: (_, input) => refresh(input.userId),
  onError: (_, input) => {
    if (canApplyNotificationResult(input.userId, auth.user?.id))
      actionError.value = 'Unable to update notification.'
  },
})
const readAll = useMutation({
  mutationFn: (sourceUserId: number) => notificationApi.markAllRead().then(() => sourceUserId),
  onSuccess: refresh,
  onError: (_, sourceUserId) => {
    if (canApplyNotificationResult(sourceUserId, auth.user?.id))
      actionError.value = 'Unable to mark notifications as read.'
  },
})
async function visit(notification: OpsNotification) {
  const sourceUserId = auth.user?.id
  if (!sourceUserId) return
  actionError.value = ''
  try {
    if (isUnread(notification)) await notificationApi.markRead(notification.id)
    if (!canApplyNotificationResult(sourceUserId, auth.user?.id)) return
    const plan = notificationNavigationPlan(notification, workspaceId.value)
    if (plan.kind === 'unavailable') {
      actionError.value = 'This notification has no available destination.'
      await refresh(sourceUserId)
      return
    }
    if (plan.kind === 'switch') {
      await workspace.selectWorkspace(plan.workspaceId)
      if (workspace.currentWorkspaceId !== plan.workspaceId) {
        toast.error('The notification workspace is no longer available.')
        await refresh(sourceUserId)
        return
      }
    }
    open.value = false
    await router.push(plan.destination)
    await refresh(sourceUserId)
  } catch {
    if (canApplyNotificationResult(sourceUserId, auth.user?.id)) {
      actionError.value = 'Unable to open notification.'
      await refresh(sourceUserId)
    }
  }
}
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <Button
        variant="ghost"
        size="icon"
        class="relative"
        :aria-label="
          summary.unreadCount === null
            ? 'Open notifications'
            : `Open notifications, ${summary.unreadCount} unread`
        "
      >
        <Bell class="size-5" />
        <span
          v-if="summary.unreadCount"
          class="absolute -right-1 -top-1 min-w-5 rounded-full bg-primary px-1 text-center text-[10px] leading-5 text-primary-foreground"
          >{{ unreadBadge(summary.unreadCount) }}</span
        >
      </Button>
    </PopoverTrigger>
    <PopoverContent align="end" class="w-[min(24rem,calc(100vw-1rem))] p-0">
      <div class="flex items-center justify-between border-b p-4">
        <h2 class="font-semibold">Notifications</h2>
        <Button
          v-if="summary.unreadCount"
          variant="ghost"
          size="sm"
          :disabled="readAll.isPending.value"
          @click="auth.user && readAll.mutate(auth.user.id)"
          ><CheckCheck />Mark all read</Button
        >
      </div>
      <p v-if="actionError" class="px-4 pt-3 text-sm text-destructive">{{ actionError }}</p>
      <div v-if="recent.isPending.value" class="space-y-3 p-4">
        <Skeleton v-for="item in 3" :key="item" class="h-16 w-full" />
      </div>
      <div v-else-if="recent.isError.value" class="p-4 text-sm text-destructive">
        Unable to load notifications.
        <Button variant="link" class="h-auto p-0" @click="recent.refetch()">Retry</Button>
      </div>
      <p
        v-else-if="!recent.data.value?.data.length"
        class="p-6 text-center text-sm text-muted-foreground"
      >
        No notifications yet.
      </p>
      <div v-else class="max-h-[60vh] overflow-y-auto">
        <NotificationItem
          v-for="notification in recent.data.value?.data"
          :key="notification.id"
          :notification="notification"
          :busy="toggle.isPending.value"
          @open="visit(notification)"
          @toggle="auth.user && toggle.mutate({ userId: auth.user.id, notification })"
        />
      </div>
      <RouterLink
        class="block border-t p-3 text-center text-sm font-medium hover:bg-muted"
        to="/notifications"
        @click="open = false"
      >
        View all notifications
      </RouterLink>
    </PopoverContent>
  </Popover>
</template>

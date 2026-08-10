<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useRoute, useRouter } from 'vue-router'
import { CheckCheck } from '@lucide/vue'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import ServerPagination from '@/components/app/table/ServerPagination.vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkspaceStore } from '@/stores/workspace'
import { useNotificationStore } from '@/stores/notifications'
import { useAuthStore } from '@/stores/auth'
import { notificationApi } from '../api/notifications'
import NotificationItem from '../components/NotificationItem.vue'
import { notificationKeys } from '../queries/notificationKeys'
import type { NotificationStatus, OpsNotification } from '../types/notification'
import { canApplyNotificationResult, isUnread } from '../notificationPresentation'
import { notificationNavigationPlan } from '../notificationNavigation'

const route = useRoute(),
  router = useRouter(),
  workspace = useWorkspaceStore(),
  summary = useNotificationStore(),
  auth = useAuthStore(),
  client = useQueryClient()
const actionError = ref('')
const page = computed(() => Math.max(1, Number(route.query.page) || 1))
const status = computed<NotificationStatus>(() =>
  ['unread', 'read'].includes(String(route.query.status))
    ? (route.query.status as NotificationStatus)
    : 'all',
)
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const notifications = useQuery({
  queryKey: computed(() => notificationKeys.list(page.value, status.value)),
  queryFn: () => notificationApi.list(page.value, 20, status.value),
  enabled: () => workspaceId.value > 0,
})
function setQuery(next: { page?: number; status?: NotificationStatus }) {
  router.replace({
    query: {
      ...route.query,
      page: String(next.page ?? (next.status ? 1 : page.value)),
      status: next.status ?? status.value,
    },
  })
}
async function refresh(sourceUserId: number) {
  if (!canApplyNotificationResult(sourceUserId, auth.user?.id)) return
  await Promise.all([
    client.invalidateQueries({ queryKey: notificationKeys.all() }),
    client.invalidateQueries({ queryKey: notificationKeys.unreadCount() }),
  ])
  const count = await notificationApi.unreadCount()
  if (canApplyNotificationResult(sourceUserId, auth.user?.id)) summary.synchronizeUnreadCount(count)
}
const toggle = useMutation({
  mutationFn: (input: { userId: number; notification: OpsNotification }) =>
    isUnread(input.notification)
      ? notificationApi.markRead(input.notification.id)
      : notificationApi.markUnread(input.notification.id),
  onSuccess: (_, input) => {
    actionError.value = ''
    return refresh(input.userId)
  },
  onError: (_, input) => {
    if (canApplyNotificationResult(input.userId, auth.user?.id))
      actionError.value = 'Unable to update notification. Try again.'
  },
})
const readAll = useMutation({
  mutationFn: (sourceUserId: number) => notificationApi.markAllRead().then(() => sourceUserId),
  onSuccess: refresh,
  onError: (_, sourceUserId) => {
    if (canApplyNotificationResult(sourceUserId, auth.user?.id))
      actionError.value = 'Unable to mark notifications as read. Try again.'
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
        actionError.value = 'The notification workspace is no longer available.'
        await refresh(sourceUserId)
        return
      }
    }
    await router.push(plan.destination)
    await refresh(sourceUserId)
  } catch {
    if (canApplyNotificationResult(sourceUserId, auth.user?.id)) {
      actionError.value = 'Unable to open notification. Try again.'
      await refresh(sourceUserId)
    }
  }
}
watch(workspaceId, () => {
  actionError.value = ''
})
</script>

<template>
  <AppShell>
    <PageHeader title="Notifications" description="Updates about requests and approvals.">
      <template #actions>
        <Button
          :disabled="readAll.isPending.value"
          @click="auth.user && readAll.mutate(auth.user.id)"
        >
          <CheckCheck />Mark all as read
        </Button>
      </template>
    </PageHeader>
    <p v-if="actionError" class="mb-4 text-sm text-destructive">{{ actionError }}</p>
    <div class="mb-4 flex gap-2" aria-label="Notification status">
      <Button
        v-for="option in ['all', 'unread', 'read'] as const"
        :key="option"
        size="sm"
        :variant="status === option ? 'default' : 'outline'"
        @click="setQuery({ status: option })"
        >{{ option.charAt(0).toUpperCase() + option.slice(1) }}</Button
      >
    </div>
    <div class="overflow-hidden rounded-lg border bg-card">
      <div v-if="notifications.isPending.value" class="space-y-3 p-4">
        <Skeleton v-for="item in 5" :key="item" class="h-20 w-full" />
      </div>
      <div v-else-if="notifications.isError.value" class="p-6 text-sm text-destructive">
        Unable to load notifications.
        <Button variant="link" class="h-auto p-0" @click="notifications.refetch()">Retry</Button>
      </div>
      <p
        v-else-if="!notifications.data.value?.data.length"
        class="p-10 text-center text-sm text-muted-foreground"
      >
        No {{ status === 'all' ? '' : status }} notifications.
      </p>
      <NotificationItem
        v-for="notification in notifications.data.value?.data ?? []"
        v-else
        :key="notification.id"
        :notification="notification"
        :busy="toggle.isPending.value"
        @open="visit(notification)"
        @toggle="auth.user && toggle.mutate({ userId: auth.user.id, notification })"
      />
    </div>
    <div v-if="(notifications.data.value?.meta.last_page ?? 1) > 1" class="mt-5 flex justify-end">
      <ServerPagination
        :page="page"
        :page-count="notifications.data.value?.meta.last_page ?? 1"
        @update:page="setQuery({ page: $event })"
      />
    </div>
  </AppShell>
</template>

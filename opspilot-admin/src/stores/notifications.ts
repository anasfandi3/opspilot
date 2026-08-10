import { ref } from 'vue'
import { defineStore } from 'pinia'

export const useNotificationStore = defineStore('notifications', () => {
  const unreadCount = ref<number | null>(null)
  function synchronizeUnreadCount(count: number) {
    unreadCount.value = Math.max(0, count)
  }
  function reset() {
    unreadCount.value = null
  }
  return { unreadCount, synchronizeUnreadCount, reset }
})

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { LogOut, Menu } from '@lucide/vue'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import ThemeToggle from '@/components/app/ThemeToggle.vue'
import WorkspaceSwitcher from '@/features/workspaces/components/WorkspaceSwitcher.vue'
import NotificationBell from '@/features/notifications/components/NotificationBell.vue'
import { useAuthStore } from '@/stores/auth'
defineProps<{ demo?: boolean }>()
defineEmits<{ menu: [] }>()
const auth = useAuthStore()
const router = useRouter()
const initials = computed(
  () =>
    auth.user?.name
      .split(' ')
      .map((part) => part[0])
      .join('')
      .slice(0, 2)
      .toUpperCase() ?? 'OP',
)
async function logout() {
  await auth.logout()
  await router.replace('/login')
}
</script>
<template>
  <header
    class="sticky top-0 z-30 flex h-16 items-center justify-between border-b bg-background/90 px-4 backdrop-blur md:px-6"
  >
    <div class="flex min-w-0 items-center gap-3">
      <Button
        variant="ghost"
        size="icon"
        class="shrink-0 md:hidden"
        aria-label="Open navigation"
        data-testid="mobile-menu"
        @click="$emit('menu')"
        ><Menu class="size-5" /></Button
      ><span v-if="demo" class="text-sm text-muted-foreground">Design system</span
      ><WorkspaceSwitcher v-else />
    </div>
    <div class="flex items-center gap-2">
      <NotificationBell v-if="!demo" />
      <ThemeToggle />
      <div class="h-6 w-px bg-border" />
      <div v-if="demo" class="flex items-center gap-2">
        <Avatar class="size-8"><AvatarFallback>OP</AvatarFallback></Avatar>
        <div class="hidden text-sm sm:block">
          <p class="font-medium leading-tight">Demo user</p>
          <p class="text-xs text-muted-foreground">UI preview</p>
        </div>
      </div>
      <DropdownMenu v-else
        ><DropdownMenuTrigger as-child
          ><Button variant="ghost" class="h-auto gap-2 px-2" aria-label="Open account menu"
            ><Avatar class="size-8"
              ><AvatarFallback>{{ initials }}</AvatarFallback></Avatar
            >
            <div class="hidden text-left text-sm sm:block">
              <p class="font-medium leading-tight">{{ auth.user?.name }}</p>
              <p class="max-w-48 truncate text-xs text-muted-foreground">{{ auth.user?.email }}</p>
            </div></Button
          ></DropdownMenuTrigger
        ><DropdownMenuContent align="end" class="w-64"
          ><DropdownMenuLabel
            ><p>{{ auth.user?.name }}</p>
            <p class="text-xs font-normal text-muted-foreground">
              {{ auth.user?.email }}
            </p></DropdownMenuLabel
          ><DropdownMenuSeparator /><DropdownMenuItem @select="logout"
            ><LogOut />Log out</DropdownMenuItem
          ></DropdownMenuContent
        ></DropdownMenu
      >
    </div>
  </header>
</template>

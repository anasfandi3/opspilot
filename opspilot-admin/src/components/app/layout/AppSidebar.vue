<script setup lang="ts">
import {
  BarChart3,
  CheckSquare,
  FileStack,
  GitBranch,
  LayoutDashboard,
  PanelLeftClose,
  PanelLeftOpen,
  Settings2,
  Users,
} from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { RouterLink } from 'vue-router'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { useAuthorization } from '@/composables/useAuthorization'

defineProps<{ collapsed?: boolean; demo?: boolean }>()
const emit = defineEmits<{ toggle: []; navigate: [] }>()
const { can } = useAuthorization()
const settingsItems = [
  {
    to: '/request-types',
    label: 'Request Types',
    icon: FileStack,
    permission: 'request_types.view',
  },
  { to: '/settings/workspace', label: 'Workspace', icon: Settings2, permission: 'workspace.view' },
  { to: '/settings/members', label: 'Members', icon: Users, permission: 'members.view' },
  {
    to: '/settings/invitations',
    label: 'Invitations',
    icon: Users,
    permission: 'invitations.view',
  },
] as const
const items = [
  [LayoutDashboard, 'Dashboard'],
  [FileStack, 'Requests'],
  [CheckSquare, 'Approvals'],
  [Settings2, 'Request Types'],
  [GitBranch, 'Workflows'],
  [Users, 'Members'],
  [BarChart3, 'Reports'],
] as const
</script>

<template>
  <TooltipProvider :delay-duration="200">
    <aside
      class="flex h-full flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground"
    >
      <div
        class="flex h-16 items-center border-b border-sidebar-border px-3"
        :class="collapsed ? 'justify-center' : 'gap-3 px-5'"
      >
        <div
          class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary text-sm font-bold text-primary-foreground"
        >
          O
        </div>
        <div v-if="!collapsed" class="min-w-0">
          <p class="font-semibold">OpsPilot</p>
          <p class="text-xs text-muted-foreground">Operations admin</p>
        </div>
      </div>
      <nav class="flex-1 space-y-1 p-3" aria-label="Primary navigation">
        <Tooltip v-if="!demo">
          <TooltipTrigger as-child
            ><RouterLink
              to="/home"
              class="flex h-10 w-full items-center rounded-md bg-sidebar-accent text-sm font-medium text-sidebar-accent-foreground"
              :class="collapsed ? 'justify-center' : 'gap-3 px-3'"
              :aria-label="collapsed ? 'Home' : undefined"
              @click="emit('navigate')"
              ><LayoutDashboard class="size-4" /><span v-if="!collapsed">Home</span></RouterLink
            ></TooltipTrigger
          ><TooltipContent v-if="collapsed" side="right">Home</TooltipContent>
        </Tooltip>
        <template v-if="!demo">
          <Tooltip
            v-for="item in settingsItems.filter((entry) => can(entry.permission))"
            :key="item.to"
          >
            <TooltipTrigger as-child
              ><RouterLink
                :to="item.to"
                class="flex h-10 w-full items-center rounded-md text-sm font-medium transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                :class="collapsed ? 'justify-center' : 'gap-3 px-3'"
                active-class="bg-sidebar-accent text-sidebar-accent-foreground"
                :aria-label="collapsed ? item.label : undefined"
                @click="emit('navigate')"
                ><component :is="item.icon" class="size-4" /><span v-if="!collapsed">{{
                  item.label
                }}</span></RouterLink
              ></TooltipTrigger
            >
            <TooltipContent v-if="collapsed" side="right">{{ item.label }}</TooltipContent>
          </Tooltip>
        </template>
        <Tooltip v-for="([icon, label], index) in demo ? items : []" :key="label">
          <TooltipTrigger as-child>
            <button
              type="button"
              class="flex h-10 w-full items-center rounded-md text-sm font-medium transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:outline-2 focus-visible:outline-ring"
              :class="[
                collapsed ? 'justify-center' : 'gap-3 px-3',
                index === 0 && 'bg-sidebar-accent text-sidebar-accent-foreground',
              ]"
              :aria-label="collapsed ? label : undefined"
            >
              <component :is="icon" class="size-4 shrink-0" /><span v-if="!collapsed">{{
                label
              }}</span>
            </button>
          </TooltipTrigger>
          <TooltipContent v-if="collapsed" side="right">{{ label }}</TooltipContent>
        </Tooltip>
      </nav>
      <div class="border-t border-sidebar-border p-3">
        <Button
          variant="ghost"
          class="w-full"
          :class="collapsed ? 'px-0' : 'justify-start'"
          :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
          data-testid="sidebar-toggle"
          @click="emit('toggle')"
        >
          <PanelLeftOpen v-if="collapsed" class="size-4" /><PanelLeftClose
            v-else
            class="size-4"
          /><span v-if="!collapsed">Collapse sidebar</span>
        </Button>
      </div>
    </aside>
  </TooltipProvider>
</template>

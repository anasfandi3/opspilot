<script setup lang="ts">
import { Building2, Check, ChevronsUpDown, Loader2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useWorkspaceStore } from '@/stores/workspace'
const store = useWorkspaceStore()
</script>
<template>
  <DropdownMenu
    ><DropdownMenuTrigger as-child
      ><Button variant="ghost" class="max-w-56 justify-start px-2" aria-label="Switch workspace"
        ><Building2 /><span class="truncate">{{
          store.currentWorkspace?.name ?? 'No workspace'
        }}</span
        ><Loader2 v-if="store.switching" class="ml-auto animate-spin" /><ChevronsUpDown
          v-else
          class="ml-auto" /></Button></DropdownMenuTrigger
    ><DropdownMenuContent align="start" class="w-64"
      ><DropdownMenuLabel>Workspaces</DropdownMenuLabel><DropdownMenuSeparator /><DropdownMenuItem
        v-for="workspace in store.workspaces"
        :key="workspace.id"
        :disabled="store.switching || workspace.id === store.currentWorkspaceId"
        @select="store.selectWorkspace(workspace.id)"
        ><span class="truncate">{{ workspace.name }}</span
        ><Check
          v-if="workspace.id === store.currentWorkspaceId"
          class="ml-auto" /></DropdownMenuItem
      ><DropdownMenuItem v-if="!store.workspaces.length" disabled
        >No available workspaces</DropdownMenuItem
      ></DropdownMenuContent
    ></DropdownMenu
  >
</template>

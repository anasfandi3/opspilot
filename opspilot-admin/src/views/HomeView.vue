<script setup lang="ts">
import { Building2 } from '@lucide/vue'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import { Card, CardContent } from '@/components/ui/card'
import { useWorkspaceStore } from '@/stores/workspace'
const workspace = useWorkspaceStore()
</script>
<template>
  <AppShell
    ><PageHeader
      title="Welcome to OpsPilot"
      description="Choose an available area from the navigation to continue."
    /><Card
      ><CardContent class="flex items-center gap-4 pt-6"
        ><div class="rounded-lg bg-muted p-3"><Building2 class="size-5" /></div>
        <div>
          <p class="font-medium">
            {{ workspace.currentWorkspace?.name ?? 'No workspace available' }}
          </p>
          <p class="text-sm text-muted-foreground">
            You are working in this workspace. Available navigation reflects your permissions.
          </p>
        </div></CardContent
      ></Card
    ></AppShell
  >
</template>

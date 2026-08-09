<script setup lang="ts">
import { Plus } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import FoundationFormsSection from './ui/FoundationFormsSection.vue'
import FeedbackOverlaySection from './ui/FeedbackOverlaySection.vue'
import TableSection from './ui/TableSection.vue'
</script>
<template>
  <AppShell demo
    ><PageHeader
      title="UI foundation"
      description="Reusable visual primitives and application patterns for future OpsPilot administration screens."
      ><template #actions
        ><Button variant="outline">Secondary action</Button
        ><Button><Plus />Primary action</Button></template
      ></PageHeader
    >
    <div class="space-y-12">
      <FoundationFormsSection /><FeedbackOverlaySection /><TableSection /></div
  ></AppShell>
</template>

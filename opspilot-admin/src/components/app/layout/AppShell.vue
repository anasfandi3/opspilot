<script setup lang="ts">
import { ref } from 'vue'
import AppHeader from './AppHeader.vue'
import AppSidebar from './AppSidebar.vue'
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet'
import { useSidebar } from '@/composables/useSidebar'
const { collapsed } = useSidebar()
const mobileOpen = ref(false)
withDefaults(defineProps<{ demo?: boolean }>(), { demo: false })
</script>
<template>
  <div class="min-h-screen bg-muted/20">
    <div
      class="fixed inset-y-0 left-0 z-40 hidden transition-[width] duration-200 md:block"
      :class="collapsed ? 'w-18' : 'w-64'"
    >
      <AppSidebar :collapsed="collapsed" :demo="demo" @toggle="collapsed = !collapsed" />
    </div>
    <Sheet v-model:open="mobileOpen"
      ><SheetContent side="left" class="w-72 p-0"
        ><SheetTitle class="sr-only">Navigation</SheetTitle
        ><SheetDescription class="sr-only">OpsPilot primary navigation</SheetDescription
        ><AppSidebar
          :demo="demo"
          @toggle="mobileOpen = false"
          @navigate="mobileOpen = false" /></SheetContent
    ></Sheet>
    <div
      class="min-w-0 transition-[margin] duration-200"
      :class="collapsed ? 'md:ml-18' : 'md:ml-64'"
    >
      <AppHeader :demo="demo" @menu="mobileOpen = true" />
      <main class="w-full min-w-0 overflow-x-hidden p-4 md:p-6 lg:p-8"><slot /></main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Menu } from '@lucide/vue'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import ThemeToggle from '@/components/app/ThemeToggle.vue'
defineEmits<{ menu: [] }>()
</script>
<template>
  <header
    class="sticky top-0 z-30 flex h-16 items-center justify-between border-b bg-background/90 px-4 backdrop-blur md:px-6"
  >
    <div class="flex items-center gap-3">
      <Button
        variant="ghost"
        size="icon"
        class="md:hidden"
        aria-label="Open navigation"
        data-testid="mobile-menu"
        @click="$emit('menu')"
        ><Menu class="size-5" /></Button
      ><span class="text-sm text-muted-foreground">Design system</span>
    </div>
    <div class="flex items-center gap-2">
      <ThemeToggle />
      <div class="h-6 w-px bg-border" />
      <div class="flex items-center gap-2">
        <Avatar class="size-8"><AvatarFallback>OP</AvatarFallback></Avatar>
        <div class="hidden text-sm sm:block">
          <p class="font-medium leading-tight">Demo user</p>
          <p class="text-xs text-muted-foreground">UI preview</p>
        </div>
      </div>
    </div>
  </header>
</template>

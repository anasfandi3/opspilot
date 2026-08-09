<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { ShieldX, FileQuestion } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/stores/auth'
const route = useRoute()
const auth = useAuthStore()
const forbidden = computed(() => route.name === 'forbidden')
</script>
<template>
  <main class="grid min-h-screen place-items-center bg-muted/20 p-6 text-center">
    <div>
      <component
        :is="forbidden ? ShieldX : FileQuestion"
        class="mx-auto mb-5 size-12 text-muted-foreground"
      />
      <p class="text-sm font-medium text-muted-foreground">{{ forbidden ? '403' : '404' }}</p>
      <h1 class="mt-2 text-3xl font-semibold">
        {{ forbidden ? 'Access denied' : 'Page not found' }}
      </h1>
      <p class="mx-auto mt-3 max-w-md text-sm text-muted-foreground">
        {{
          forbidden
            ? 'You do not have permission to view this page in the current workspace.'
            : 'The page you requested does not exist.'
        }}
      </p>
      <Button as-child class="mt-6"
        ><RouterLink :to="auth.authenticated ? '/home' : '/login'">{{
          auth.authenticated ? 'Back to home' : 'Go to sign in'
        }}</RouterLink></Button
      >
    </div>
  </main>
</template>

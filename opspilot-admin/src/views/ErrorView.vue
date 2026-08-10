<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ShieldX, FileQuestion } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/stores/auth'
import { useAuthorization } from '@/composables/useAuthorization'
import { resolveAccessibleHome } from '@/router/home'
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { can } = useAuthorization()
const forbidden = computed(() => route.name === 'forbidden')
const destination = computed(() =>
  auth.authenticated ? resolveAccessibleHome(router, can) : '/login',
)
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
        {{ forbidden ? "You don't have access" : 'Page not found' }}
      </h1>
      <p class="mx-auto mt-3 max-w-md text-sm text-muted-foreground">
        {{
          forbidden
            ? 'This area is not available with your current workspace permissions.'
            : 'The page may have moved, or the address may be incorrect.'
        }}
      </p>
      <Button as-child class="mt-6"
        ><RouterLink :to="destination">{{
          auth.authenticated ? 'Go to home' : 'Go to sign in'
        }}</RouterLink></Button
      >
    </div>
  </main>
</template>

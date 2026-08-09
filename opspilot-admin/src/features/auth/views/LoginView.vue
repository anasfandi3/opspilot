<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AlertCircle, Loader2 } from '@lucide/vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import FormField from '@/components/app/forms/FormField.vue'
import ThemeToggle from '@/components/app/ThemeToggle.vue'
import { useAuthStore } from '@/stores/auth'
import { ApiError } from '@/lib/api/errors'
import { safeRedirect, resolveAccessibleHome } from '@/router/home'
import { useAuthorization } from '@/composables/useAuthorization'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const { can } = useAuthorization()
const form = reactive({ email: '', password: '' })
const loading = ref(false)
const error = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
async function submit() {
  loading.value = true
  error.value = ''
  fieldErrors.value = {}
  try {
    await auth.login(form)
    const redirect = safeRedirect(route.query.redirect)
    await router.replace(redirect ?? resolveAccessibleHome(router, can))
  } catch (caught) {
    if (caught instanceof ApiError) {
      fieldErrors.value = caught.fieldErrors
      error.value = caught.kind === 'validation' && caught.fieldErrors.email ? '' : caught.message
    } else error.value = 'Unable to sign in.'
  } finally {
    loading.value = false
  }
}
</script>
<template>
  <main class="relative grid min-h-screen place-items-center bg-muted/30 p-4">
    <div class="absolute right-4 top-4"><ThemeToggle /></div>
    <Card class="w-full max-w-md"
      ><CardHeader class="text-center"
        ><div
          class="mx-auto mb-3 grid size-11 place-items-center rounded-xl bg-primary font-bold text-primary-foreground"
        >
          O
        </div>
        <CardTitle class="text-2xl">Sign in to OpsPilot</CardTitle
        ><CardDescription>Use your OpsPilot account to continue.</CardDescription></CardHeader
      ><CardContent
        ><form class="space-y-5" @submit.prevent="submit">
          <Alert v-if="error" variant="destructive"
            ><AlertCircle class="size-4" /><AlertTitle>Sign in failed</AlertTitle
            ><AlertDescription>{{ error }}</AlertDescription></Alert
          ><FormField
            label="Email"
            id="email"
            required
            :error="fieldErrors.email?.[0]"
            v-slot="slot"
            ><Input
              :id="slot.id"
              v-model="form.email"
              type="email"
              autocomplete="email"
              :aria-invalid="slot.invalid"
              :aria-describedby="slot.describedby" /></FormField
          ><FormField
            label="Password"
            id="password"
            required
            :error="fieldErrors.password?.[0]"
            v-slot="slot"
            ><Input
              :id="slot.id"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              :aria-invalid="slot.invalid"
              :aria-describedby="slot.describedby" /></FormField
          ><Button type="submit" class="w-full" :disabled="loading"
            ><Loader2 v-if="loading" class="animate-spin" />{{
              loading ? 'Signing in…' : 'Sign in'
            }}</Button
          >
        </form></CardContent
      ></Card
    >
  </main>
</template>

<script setup lang="ts">
import FormField from '@/components/app/forms/FormField.vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import type { RequestTypeFormModel } from '../types/requestType'
import DynamicFieldBuilder from './DynamicFieldBuilder.vue'
import DynamicFieldPreview from './DynamicFieldPreview.vue'

const props = defineProps<{
  modelValue: RequestTypeFormModel
  errors: Record<string, string[]>
  generalError?: string
  saving?: boolean
}>()
const emit = defineEmits<{
  'update:modelValue': [value: RequestTypeFormModel]
  submit: []
  structural: []
}>()
function update(value: Partial<RequestTypeFormModel>) {
  emit('update:modelValue', { ...props.modelValue, ...value })
}
</script>
<template>
  <form class="space-y-8" @submit.prevent="emit('submit')">
    <Alert v-if="generalError" variant="destructive"
      ><AlertTitle>Unable to save request type</AlertTitle
      ><AlertDescription>{{ generalError }}</AlertDescription></Alert
    >
    <section class="rounded-lg border bg-card p-5 sm:p-6">
      <h2 class="mb-5 text-xl font-semibold">Request type details</h2>
      <div class="grid gap-5 md:grid-cols-2">
        <FormField label="Name" required :error="errors.name?.[0]" v-slot="slot"
          ><Input
            :id="slot.id"
            :model-value="modelValue.name"
            maxlength="255"
            :aria-invalid="slot.invalid"
            :aria-describedby="slot.describedby"
            @update:model-value="update({ name: String($event) })"
        /></FormField>
        <div class="flex items-center gap-3 pt-7">
          <Switch
            id="request-type-active"
            :model-value="modelValue.is_active"
            @update:model-value="update({ is_active: $event })"
          /><Label for="request-type-active">Active and available</Label>
        </div>
        <FormField
          class="md:col-span-2"
          label="Description"
          :error="errors.description?.[0]"
          v-slot="slot"
          ><Textarea
            :id="slot.id"
            :model-value="modelValue.description"
            maxlength="5000"
            :aria-invalid="slot.invalid"
            :aria-describedby="slot.describedby"
            @update:model-value="update({ description: String($event) })"
        /></FormField>
      </div>
    </section>
    <DynamicFieldBuilder
      :model-value="modelValue.fields"
      :errors="errors"
      @update:model-value="update({ fields: $event })"
      @structural="emit('structural')"
    />
    <DynamicFieldPreview :fields="modelValue.fields" />
    <div class="sticky bottom-0 flex justify-end border-t bg-background/95 py-4 backdrop-blur">
      <Button type="submit" size="lg" :disabled="saving">{{
        saving ? 'Saving…' : 'Save request type'
      }}</Button>
    </div>
  </form>
</template>

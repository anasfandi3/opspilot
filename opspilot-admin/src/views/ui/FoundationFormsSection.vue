<script setup lang="ts">
import { ref, shallowRef } from 'vue'
import { CalendarDays, Loader2, Plus, Trash2 } from '@lucide/vue'
import { CalendarDate, getLocalTimeZone } from '@internationalized/date'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import FormField from '@/components/app/forms/FormField.vue'
import FormGrid from '@/components/app/forms/FormGrid.vue'
import FileInput from '@/components/app/forms/FileInput.vue'
const enabled = ref(true)
const checked = ref(false)
const date = shallowRef(new CalendarDate(2026, 8, 9))
const category = ref('operations')
</script>
<template>
  <section id="foundation" class="space-y-5">
    <div>
      <h2 class="text-xl font-semibold">Foundation & buttons</h2>
      <p class="text-sm text-muted-foreground">
        Neutral semantic tokens, Inter typography, and a restrained action hierarchy.
      </p>
    </div>
    <Card
      ><CardContent class="grid gap-6 pt-6 lg:grid-cols-2"
        ><div class="space-y-3">
          <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
            Semantic colors
          </p>
          <div class="grid grid-cols-4 gap-3">
            <div v-for="token in ['primary', 'secondary', 'muted', 'accent']" :key="token">
              <div class="h-14 rounded-md border" :class="`bg-${token}`" />
              <p class="mt-1 text-xs capitalize">{{ token }}</p>
            </div>
          </div>
        </div>
        <div class="space-y-3">
          <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
            Typography
          </p>
          <p class="text-2xl font-semibold tracking-tight">Page title</p>
          <p class="font-medium">Section heading</p>
          <p class="text-sm">Clear, comfortable body text for operational work.</p>
          <p class="text-xs text-muted-foreground">Muted metadata and helper text</p>
        </div></CardContent
      ></Card
    >
    <div class="flex flex-wrap gap-2">
      <Button>Primary</Button><Button variant="secondary">Secondary</Button
      ><Button variant="outline">Outline</Button><Button variant="ghost">Ghost</Button
      ><Button variant="destructive"><Trash2 />Destructive</Button
      ><Button size="icon" aria-label="Add item"><Plus /></Button
      ><Button disabled><Loader2 class="animate-spin" />Loading</Button>
    </div>
  </section>
  <section id="forms" class="space-y-5">
    <div>
      <h2 class="text-xl font-semibold">Form controls</h2>
      <p class="text-sm text-muted-foreground">
        Labels remain above controls with direct helper and validation feedback.
      </p>
    </div>
    <Card
      ><CardHeader
        ><CardTitle>Adaptive form</CardTitle
        ><CardDescription
          >Two columns on desktop and one column on mobile.</CardDescription
        ></CardHeader
      ><CardContent
        ><FormGrid :columns="2"
          ><FormField
            label="Request title"
            required
            description="Use a concise, recognizable name."
            v-slot="slot"
            ><Input
              v-bind="{ id: slot.id, 'aria-describedby': slot.describedby }"
              placeholder="Quarterly access review" /></FormField
          ><FormField label="Category" v-slot="slot"
            ><Select v-model="category"
              ><SelectTrigger :id="slot.id"><SelectValue /></SelectTrigger
              ><SelectContent
                ><SelectItem value="operations">Operations</SelectItem
                ><SelectItem value="finance">Finance</SelectItem
                ><SelectItem value="people">People</SelectItem></SelectContent
              ></Select
            ></FormField
          ><FormField label="Owner email" error="Enter a valid work email." v-slot="slot"
            ><Input
              v-bind="{
                id: slot.id,
                'aria-describedby': slot.describedby,
                'aria-invalid': slot.invalid,
              }"
              value="not-an-email" /></FormField
          ><FormField
            label="Due date"
            description="Select a date from the accessible calendar."
            v-slot="slot"
            ><Popover
              ><PopoverTrigger as-child
                ><Button :id="slot.id" variant="outline" class="w-full justify-start font-normal"
                  ><CalendarDays />{{
                    date.toDate(getLocalTimeZone()).toLocaleDateString()
                  }}</Button
                ></PopoverTrigger
              ><PopoverContent class="w-auto p-0"
                ><Calendar v-model="date" /></PopoverContent></Popover></FormField
          ><FormField label="Description" class="md:col-span-2" v-slot="slot"
            ><Textarea :id="slot.id" rows="4" placeholder="Add context for reviewers…" /></FormField
          ><FormField
            label="Supporting file"
            class="md:col-span-2"
            description="PDF or image, no upload is performed."
            v-slot="slot"
            ><FileInput :id="slot.id" accept=".pdf,image/*"
          /></FormField>
          <div class="flex items-center gap-3">
            <Checkbox id="notify" v-model="checked" /><label
              for="notify"
              class="text-sm font-medium"
              >Notify reviewers</label
            >
          </div>
          <div class="flex items-center justify-between rounded-md border px-3 py-2">
            <label for="automation" class="text-sm font-medium">Enable automation</label
            ><Switch id="automation" v-model="enabled" /></div></FormGrid></CardContent
    ></Card>
  </section>
</template>

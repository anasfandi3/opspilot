<script setup lang="ts">
import { ref } from 'vue'
import { Info, Trash2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet'
import { Input } from '@/components/ui/input'
import ConfirmDialog from '@/components/app/ConfirmDialog.vue'
import EmptyState from '@/components/app/feedback/EmptyState.vue'
import LoadingState from '@/components/app/feedback/LoadingState.vue'
const loading = ref(false)
</script>
<template>
  <section id="feedback" class="space-y-5">
    <div>
      <h2 class="text-xl font-semibold">Feedback & loading</h2>
      <p class="text-sm text-muted-foreground">
        Inline context, bottom-right toasts, skeletons, and restrained empty states.
      </p>
    </div>
    <div class="grid gap-5 lg:grid-cols-2">
      <div class="space-y-4">
        <Alert
          ><Info class="size-4" /><AlertTitle>Design-system preview</AlertTitle
          ><AlertDescription>No backend data is used on this route.</AlertDescription></Alert
        >
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" @click="toast.success('Changes saved successfully')"
            >Success toast</Button
          ><Button variant="outline" @click="toast.error('Something needs attention')"
            >Error toast</Button
          ><Button variant="outline" @click="toast.info('UI foundation is ready')"
            >Info toast</Button
          >
        </div>
        <EmptyState
          title="Nothing to review"
          description="New records will appear here when data is connected."
          ><template #action><Button size="sm">Create example</Button></template></EmptyState
        >
      </div>
      <Card
        ><CardHeader
          ><CardTitle>Content loading</CardTitle
          ><CardDescription
            >Skeletons preserve the layout while data is loading.</CardDescription
          ></CardHeader
        ><CardContent><LoadingState :rows="3" /></CardContent
      ></Card>
    </div>
  </section>
  <section id="overlays" class="space-y-5">
    <div>
      <h2 class="text-xl font-semibold">Overlay conventions</h2>
      <p class="text-sm text-muted-foreground">
        Dialogs for focused tasks, sheets for context-preserving work, confirmations for destructive
        actions.
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <Dialog
        ><DialogTrigger as-child><Button variant="outline">Open dialog</Button></DialogTrigger
        ><DialogContent
          ><DialogHeader
            ><DialogTitle>Focused interaction</DialogTitle
            ><DialogDescription
              >Use a modal for a short, self-contained task.</DialogDescription
            ></DialogHeader
          ><Input aria-label="Example name" placeholder="Example name" /><DialogFooter
            ><Button>Save example</Button></DialogFooter
          ></DialogContent
        ></Dialog
      ><Sheet
        ><SheetTrigger as-child><Button variant="outline">Open drawer</Button></SheetTrigger
        ><SheetContent
          ><SheetHeader
            ><SheetTitle>Edit details</SheetTitle
            ><SheetDescription
              >Sheets retain the surrounding page context.</SheetDescription
            ></SheetHeader
          >
          <div class="p-4"><Input aria-label="Drawer title" placeholder="Title" /></div>
          <SheetFooter><Button>Apply changes</Button></SheetFooter></SheetContent
        ></Sheet
      ><ConfirmDialog
        title="Remove this example?"
        description="This demonstrates a generic destructive confirmation pattern."
        confirm-text="Remove"
        destructive
        :loading="loading"
        @confirm="loading = !loading"
        ><template #trigger
          ><Button variant="destructive"><Trash2 />Confirm action</Button></template
        ></ConfirmDialog
      >
    </div>
  </section>
</template>

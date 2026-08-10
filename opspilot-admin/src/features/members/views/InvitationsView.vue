<script setup lang="ts">
import { computed, h, reactive, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import type { ColumnDef } from '@tanstack/vue-table'
import { toast } from 'vue-sonner'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataTable from '@/components/app/table/DataTable.vue'
import ConfirmDialog from '@/components/app/ConfirmDialog.vue'
import FormField from '@/components/app/forms/FormField.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { ApiError } from '@/lib/api/errors'
import { useAuthorization } from '@/composables/useAuthorization'
import { useWorkspaceStore } from '@/stores/workspace'
import { invitationsApi } from '../api/members'
import { invitationsKeys } from '../queries/memberKeys'
import type { InvitationRole, WorkspaceInvitation, WorkspaceRole } from '../types/member'
import { invitationRoleOptions, roleLabel } from '../rolePresentation'
import {
  invitationActionPermissions,
  invitationActionVisibility,
  canApplyAdministrationResult,
  invitationCreateInput,
  invitationMutationInput,
  resetInvitationTransientState,
} from '../administration'

const workspace = useWorkspaceStore()
const { can } = useAuthorization()
const client = useQueryClient()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const query = useQuery({
  queryKey: computed(() => invitationsKeys.list(workspaceId.value)),
  queryFn: () => invitationsApi.list(workspaceId.value),
  enabled: computed(() => workspaceId.value > 0),
})
const inviteOpen = ref(false)
const revoking = ref<WorkspaceInvitation | null>(null)
const form = reactive<{
  email: string
  role: InvitationRole
  emailError: string
  roleError: string
  general: string
}>({ email: '', role: 'requester', emailError: '', roleError: '', general: '' })
const roleOptions = computed(() =>
  invitationRoleOptions((workspace.currentWorkspace?.role as WorkspaceRole | null) ?? null),
)
function status(item: WorkspaceInvitation) {
  if (item.accepted_at) return 'Accepted'
  if (item.revoked_at) return 'Revoked'
  if (new Date(item.expires_at) <= new Date()) return 'Expired'
  return 'Pending'
}
function pending(item: WorkspaceInvitation) {
  return status(item) === 'Pending'
}
function reset() {
  form.email = ''
  form.role = 'requester'
  form.emailError = ''
  form.roleError = ''
  form.general = ''
}
watch(workspaceId, () => {
  resetInvitationTransientState({ inviteOpen, revoking, form })
})
const createMutation = useMutation({
  mutationFn: (input: ReturnType<typeof invitationCreateInput>) =>
    invitationsApi.create(input.workspaceId, { email: input.email, role: input.role }),
  onSuccess: async (_, input) => {
    if (!canApplyAdministrationResult(input.workspaceId, workspaceId.value)) return
    await client.invalidateQueries({ queryKey: invitationsKeys.list(input.workspaceId) })
    inviteOpen.value = false
    reset()
    toast.success('Invitation sent')
  },
  onError: (e, input) => {
    if (!canApplyAdministrationResult(input.workspaceId, workspaceId.value)) return
    if (e instanceof ApiError) {
      form.emailError = e.fieldErrors.email?.[0] ?? ''
      form.roleError = e.fieldErrors.role?.[0] ?? ''
    }
    form.general = e instanceof Error ? e.message : 'Unable to create invitation.'
  },
})
function submit() {
  form.emailError = ''
  form.roleError = ''
  form.general = ''
  if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) {
    form.emailError = 'Enter a valid email address.'
    return
  }
  createMutation.mutate(invitationCreateInput(workspaceId.value, form.email.trim(), form.role))
}
const revokeMutation = useMutation({
  mutationFn: (input: ReturnType<typeof invitationMutationInput>) =>
    invitationsApi.revoke(input.workspaceId, input.invitationId),
  onSuccess: async (_, input) => {
    if (!canApplyAdministrationResult(input.workspaceId, workspaceId.value)) return
    await client.invalidateQueries({ queryKey: invitationsKeys.list(input.workspaceId) })
    revoking.value = null
    toast.success('Invitation revoked')
  },
  onError: (e: Error, input) => {
    if (!canApplyAdministrationResult(input.workspaceId, workspaceId.value)) return
    toast.error(e.message)
    revoking.value = null
  },
})
const resendMutation = useMutation({
  mutationFn: (input: ReturnType<typeof invitationMutationInput>) =>
    invitationsApi.resend(input.workspaceId, input.invitationId),
  onSuccess: async (_, input) => {
    if (!canApplyAdministrationResult(input.workspaceId, workspaceId.value)) return
    await client.invalidateQueries({ queryKey: invitationsKeys.list(input.workspaceId) })
    toast.success('Invitation resent')
  },
  onError: (e: Error, input) => {
    if (canApplyAdministrationResult(input.workspaceId, workspaceId.value)) toast.error(e.message)
  },
})
function actionCell(item: WorkspaceInvitation) {
  const visible = invitationActionVisibility(
    pending(item),
    can(invitationActionPermissions.resend),
    can(invitationActionPermissions.revoke),
  )
  if (!visible.resend && !visible.revoke) return null
  return h('div', { class: 'flex gap-2' }, [
    visible.resend
      ? h(
          Button,
          {
            size: 'sm',
            variant: 'outline',
            disabled: resendMutation.isPending.value,
            onClick: () =>
              resendMutation.mutate(invitationMutationInput(workspaceId.value, item.id)),
          },
          () => 'Resend',
        )
      : null,
    visible.revoke
      ? h(
          Button,
          { size: 'sm', variant: 'ghost', onClick: () => (revoking.value = item) },
          () => 'Revoke',
        )
      : null,
  ])
}
const columns: ColumnDef<WorkspaceInvitation>[] = [
  { accessorKey: 'email', header: 'Email' },
  {
    accessorKey: 'role',
    header: 'Role',
    cell: ({ row }) => h(Badge, { variant: 'secondary' }, () => roleLabel(row.original.role)),
  },
  {
    id: 'status',
    header: 'Status',
    cell: ({ row }) =>
      h(Badge, { variant: status(row.original) === 'Pending' ? 'default' : 'outline' }, () =>
        status(row.original),
      ),
  },
  {
    accessorKey: 'expires_at',
    header: 'Expires',
    cell: ({ row }) => new Date(row.original.expires_at).toLocaleDateString(),
  },
  {
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) => actionCell(row.original),
  },
]
</script>
<template>
  <AppShell
    ><PageHeader title="Invitations" description="Invite people and track workspace invitations."
      ><template v-if="can('invitations.create')" #actions
        ><Button @click="inviteOpen = true">Invite member</Button></template
      ></PageHeader
    >
    <div v-if="query.isError.value" class="rounded-lg border p-6 text-sm text-destructive">
      {{ query.error.value?.message }}
      <Button variant="outline" class="ml-3" @click="query.refetch()">Retry</Button>
    </div>
    <DataTable
      v-else
      :columns="columns"
      :data="query.data.value ?? []"
      :total="query.data.value?.length ?? 0"
      :page="1"
      :per-page="Math.max(query.data.value?.length ?? 0, 1)"
      :loading="query.isPending.value"
      :selectable="false"
      :page-sizes="[Math.max(query.data.value?.length ?? 0, 1)]"
    />
    <Sheet v-model:open="inviteOpen"
      ><SheetContent
        ><SheetHeader
          ><SheetTitle>Invite member</SheetTitle
          ><SheetDescription>Send access to the active workspace.</SheetDescription></SheetHeader
        >
        <form class="space-y-5 px-4" @submit.prevent="submit">
          <FormField label="Email" :error="form.emailError" v-slot="slot"
            ><Input
              :id="slot.id"
              v-model="form.email"
              type="email"
              autocomplete="email"
              :aria-invalid="slot.invalid"
              :aria-describedby="slot.describedby" /></FormField
          ><FormField label="Role" :error="form.roleError" v-slot="slot"
            ><Select v-model="form.role" :aria-labelledby="slot.id"
              ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
              ><SelectContent
                ><SelectItem v-for="option in roleOptions" :key="option" :value="option">{{
                  roleLabel(option)
                }}</SelectItem></SelectContent
              ></Select
            ></FormField
          >
          <p
            v-if="form.general && !form.emailError && !form.roleError"
            class="text-sm text-destructive"
          >
            {{ form.general }}
          </p>
          <SheetFooter class="px-0"
            ><Button type="submit" :disabled="createMutation.isPending.value">{{
              createMutation.isPending.value ? 'Sending…' : 'Send invitation'
            }}</Button></SheetFooter
          >
        </form></SheetContent
      ></Sheet
    >
    <ConfirmDialog
      v-if="revoking"
      :open="true"
      title="Revoke invitation?"
      :description="`Revoke the pending invitation for ${revoking.email}?`"
      confirm-text="Revoke invitation"
      destructive
      :loading="revokeMutation.isPending.value"
      @cancel="revoking = null"
      @confirm="
        revoking && revokeMutation.mutate(invitationMutationInput(workspaceId, revoking.id))
      "
    />
  </AppShell>
</template>

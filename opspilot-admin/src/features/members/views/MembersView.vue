<script setup lang="ts">
import { computed, h, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import type { ColumnDef } from '@tanstack/vue-table'
import { toast } from 'vue-sonner'
import AppShell from '@/components/app/layout/AppShell.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataTable from '@/components/app/table/DataTable.vue'
import ConfirmDialog from '@/components/app/ConfirmDialog.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
import { useAuthorization } from '@/composables/useAuthorization'
import { useWorkspaceStore } from '@/stores/workspace'
import { useAuthStore } from '@/stores/auth'
import { membersApi } from '../api/members'
import { membersKeys } from '../queries/memberKeys'
import { assignableRoles, roleLabel } from '../rolePresentation'
import type { WorkspaceMember, WorkspaceRole } from '../types/member'
import {
  canApplyAdministrationResult,
  memberRemovalInput,
  resetMemberTransientState,
  roleMutationInput,
} from '../administration'

const workspace = useWorkspaceStore()
const auth = useAuthStore()
const { can } = useAuthorization()
const client = useQueryClient()
const workspaceId = computed(() => workspace.currentWorkspaceId ?? 0)
const query = useQuery({
  queryKey: computed(() => membersKeys.list(workspaceId.value)),
  queryFn: () => membersApi.list(workspaceId.value),
  enabled: computed(() => workspaceId.value > 0),
})
const editing = ref<WorkspaceMember | null>(null)
const removing = ref<WorkspaceMember | null>(null)
const role = ref<WorkspaceRole>('requester')
const error = ref('')
const roleOptions = computed(() =>
  assignableRoles((workspace.currentWorkspace?.role as WorkspaceRole | null) ?? null),
)
function canEdit(member: WorkspaceMember) {
  return (
    can('members.assign_roles') &&
    member.id !== auth.user?.id &&
    member.id !== workspace.currentWorkspace?.owner_id
  )
}
function canRemove(member: WorkspaceMember) {
  return (
    can('members.manage') &&
    member.id !== auth.user?.id &&
    member.id !== workspace.currentWorkspace?.owner_id
  )
}
function edit(member: WorkspaceMember) {
  editing.value = member
  role.value = member.role === 'owner' ? 'requester' : (member.role ?? 'requester')
  error.value = ''
}
watch(workspaceId, () => {
  resetMemberTransientState({ editing, removing, role, error })
})
const roleMutation = useMutation({
  mutationFn: (input: ReturnType<typeof roleMutationInput>) =>
    membersApi.updateRole(input.workspaceId, input.memberId, input.role),
  onSuccess: async (_, input) => {
    if (!canApplyAdministrationResult(input.workspaceId, workspaceId.value)) return
    await client.invalidateQueries({ queryKey: membersKeys.list(input.workspaceId) })
    editing.value = null
    toast.success('Member role updated')
  },
  onError: (e: Error, input) => {
    if (canApplyAdministrationResult(input.workspaceId, workspaceId.value)) error.value = e.message
  },
})
const removeMutation = useMutation({
  mutationFn: (input: ReturnType<typeof memberRemovalInput>) =>
    membersApi.remove(input.workspaceId, input.memberId),
  onSuccess: async (_, input) => {
    if (!canApplyAdministrationResult(input.workspaceId, workspaceId.value)) return
    await client.invalidateQueries({ queryKey: membersKeys.list(input.workspaceId) })
    removing.value = null
    toast.success('Member removed')
  },
  onError: (e: Error, input) => {
    if (!canApplyAdministrationResult(input.workspaceId, workspaceId.value)) return
    toast.error(e.message)
    removing.value = null
  },
})
const columns: ColumnDef<WorkspaceMember>[] = [
  {
    accessorKey: 'name',
    header: 'Member',
    cell: ({ row }) =>
      h('div', [
        h('p', { class: 'font-medium' }, row.original.name),
        h('p', { class: 'text-xs text-muted-foreground' }, row.original.email),
      ]),
  },
  {
    accessorKey: 'role',
    header: 'Role',
    cell: ({ row }) => h(Badge, { variant: 'secondary' }, () => roleLabel(row.original.role)),
  },
  {
    accessorKey: 'joined_at',
    header: 'Joined',
    cell: ({ row }) => new Date(row.original.joined_at).toLocaleDateString(),
  },
  {
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) =>
      h('div', { class: 'flex gap-2' }, [
        canEdit(row.original)
          ? h(
              Button,
              { size: 'sm', variant: 'outline', onClick: () => edit(row.original) },
              () => 'Edit role',
            )
          : null,
        canRemove(row.original)
          ? h(
              Button,
              { size: 'sm', variant: 'ghost', onClick: () => (removing.value = row.original) },
              () => 'Remove',
            )
          : null,
      ]),
  },
]
</script>
<template>
  <AppShell
    ><PageHeader title="Members" description="People with access to the active workspace." />
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
    <Sheet
      :open="Boolean(editing)"
      @update:open="
        (open) => {
          if (!open) editing = null
        }
      "
      ><SheetContent
        ><SheetHeader
          ><SheetTitle>Edit member role</SheetTitle
          ><SheetDescription v-if="editing"
            >{{ editing.name }} · {{ editing.email }}</SheetDescription
          ></SheetHeader
        >
        <div class="space-y-4 px-4">
          <label class="block text-sm font-medium">Role</label
          ><Select v-model="role"
            ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
            ><SelectContent
              ><SelectItem v-for="option in roleOptions" :key="option" :value="option">{{
                roleLabel(option)
              }}</SelectItem></SelectContent
            ></Select
          >
          <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        </div>
        <SheetFooter
          ><Button
            :disabled="roleMutation.isPending.value"
            @click="
              editing && roleMutation.mutate(roleMutationInput(workspaceId, editing.id, role))
            "
            >{{ roleMutation.isPending.value ? 'Saving…' : 'Save role' }}</Button
          ></SheetFooter
        ></SheetContent
      ></Sheet
    >
    <ConfirmDialog
      v-if="removing"
      :open="true"
      title="Remove workspace member?"
      :description="`Remove ${removing.name} (${removing.email}) from this workspace?`"
      confirm-text="Remove member"
      destructive
      :loading="removeMutation.isPending.value"
      @cancel="removing = null"
      @confirm="removing && removeMutation.mutate(memberRemovalInput(workspaceId, removing.id))"
    />
  </AppShell>
</template>

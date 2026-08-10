<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { useWorkspaceStore } from '@/stores/workspace'
import { requestKeys } from '../queries/requestKeys'
import { collaborationApi } from './api'
import {
  canApplyCollaborationResult,
  commentPayload,
  downloadBlob,
  formatFileSize,
  validateUpload,
} from './helpers'
import { activityText } from './helpers'
import { ApiError } from '@/lib/api/errors'
import SectionPager from './SectionPager.vue'

const props = defineProps<{ requestId: number; canCollaborate: boolean }>()
const workspace = useWorkspaceStore(),
  client = useQueryClient()
const body = ref(''),
  commentError = ref(''),
  file = ref<File | null>(null),
  fileError = ref(''),
  sectionError = ref(''),
  commentsPage = ref(1),
  attachmentsPage = ref(1),
  activityPage = ref(1)
const workspaceId = () => workspace.currentWorkspaceId ?? 0
const comments = useQuery({
  queryKey: computed(() =>
    requestKeys.comments(workspaceId(), props.requestId, commentsPage.value),
  ),
  queryFn: () => collaborationApi.comments(workspaceId(), props.requestId, commentsPage.value),
  enabled: () => workspaceId() > 0,
})
const attachments = useQuery({
  queryKey: computed(() =>
    requestKeys.attachments(workspaceId(), props.requestId, attachmentsPage.value),
  ),
  queryFn: () =>
    collaborationApi.attachments(workspaceId(), props.requestId, attachmentsPage.value),
  enabled: () => workspaceId() > 0,
})
const activity = useQuery({
  queryKey: computed(() =>
    requestKeys.activity(workspaceId(), props.requestId, activityPage.value),
  ),
  queryFn: () => collaborationApi.activity(workspaceId(), props.requestId, activityPage.value),
  enabled: () => workspaceId() > 0,
})
watch(
  () => workspace.currentWorkspaceId,
  () => {
    body.value = ''
    file.value = null
    commentError.value = ''
    fileError.value = ''
    sectionError.value = ''
    commentsPage.value = 1
    attachmentsPage.value = 1
    activityPage.value = 1
  },
)
async function refresh(input: { workspaceId: number; requestId: number }) {
  await Promise.all([
    client.invalidateQueries({
      queryKey: requestKeys.commentsRoot(input.workspaceId, input.requestId),
    }),
    client.invalidateQueries({
      queryKey: requestKeys.attachmentsRoot(input.workspaceId, input.requestId),
    }),
    client.invalidateQueries({
      queryKey: requestKeys.activityRoot(input.workspaceId, input.requestId),
    }),
  ])
}
const addComment = useMutation({
  mutationFn: (input: { workspaceId: number; requestId: number; body: string }) =>
    collaborationApi.addComment(input.workspaceId, input.requestId, input.body),
  onSuccess: async (_, input) => {
    if (!canApplyCollaborationResult(input.workspaceId, workspaceId())) return
    body.value = ''
    commentError.value = ''
    await refresh(input)
    toast.success('Comment added')
  },
  onError: (error, input) => {
    if (!canApplyCollaborationResult(input.workspaceId, workspaceId())) return
    commentError.value =
      error instanceof ApiError
        ? (error.fieldErrors.body?.[0] ?? error.message)
        : 'Unable to add comment.'
  },
})
const upload = useMutation({
  mutationFn: (input: { workspaceId: number; requestId: number; file: File }) =>
    collaborationApi.upload(input.workspaceId, input.requestId, input.file),
  onSuccess: async (_, input) => {
    if (!canApplyCollaborationResult(input.workspaceId, workspaceId())) return
    file.value = null
    fileError.value = ''
    await refresh(input)
    toast.success('Attachment uploaded')
  },
  onError: (error, input) => {
    if (!canApplyCollaborationResult(input.workspaceId, workspaceId())) return
    fileError.value =
      error instanceof ApiError
        ? (error.fieldErrors.file?.[0] ?? error.message)
        : 'Unable to upload attachment.'
  },
})
function submitComment() {
  const payload = commentPayload(body.value)
  commentError.value = payload.body ? '' : 'Enter a comment.'
  if (!commentError.value)
    addComment.mutate({
      workspaceId: workspaceId(),
      requestId: props.requestId,
      body: payload.body,
    })
}
function selectFile(event: Event) {
  file.value = (event.target as HTMLInputElement).files?.[0] ?? null
  fileError.value = validateUpload(file.value)
}
function submitFile() {
  fileError.value = validateUpload(file.value)
  if (!fileError.value && file.value)
    upload.mutate({ workspaceId: workspaceId(), requestId: props.requestId, file: file.value })
}
async function download(id: number, name: string) {
  const source = workspaceId()
  try {
    const result = await collaborationApi.download(source, props.requestId, id)
    if (canApplyCollaborationResult(source, workspaceId()))
      downloadBlob(result.blob, result.filename ?? name)
  } catch (error) {
    if (canApplyCollaborationResult(source, workspaceId())) {
      sectionError.value = error instanceof Error ? error.message : 'Unable to download attachment.'
      toast.error(sectionError.value)
    }
  }
}
</script>
<template>
  <div class="mt-8 space-y-8">
    <section>
      <h2 class="mb-4 text-lg font-semibold">Comments</h2>
      <form v-if="canCollaborate" class="mb-5 space-y-2" @submit.prevent="submitComment">
        <label for="request-comment" class="text-sm font-medium">Add comment</label
        ><textarea
          id="request-comment"
          v-model="body"
          maxlength="5000"
          class="min-h-24 w-full rounded-md border bg-background p-3 text-sm"
        />
        <p v-if="commentError" class="text-sm text-destructive">{{ commentError }}</p>
        <Button :disabled="addComment.isPending.value">{{
          addComment.isPending.value ? 'Adding…' : 'Add comment'
        }}</Button>
      </form>
      <div v-if="comments.isError.value" class="text-sm text-destructive">
        Unable to load comments.
        <Button variant="link" class="h-auto p-0" @click="comments.refetch()">Retry</Button>
      </div>
      <ul v-else class="space-y-3">
        <li
          v-for="comment in comments.data.value?.data ?? []"
          :key="comment.id"
          class="rounded-md border p-4"
        >
          <div class="flex flex-wrap justify-between gap-2 text-sm">
            <strong>{{ comment.author.name }}</strong
            ><time class="text-muted-foreground">{{
              new Date(comment.created_at).toLocaleString()
            }}</time>
          </div>
          <p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ comment.body }}</p>
        </li>
        <li
          v-if="!comments.isPending.value && !comments.data.value?.data.length"
          class="text-sm text-muted-foreground"
        >
          No comments yet.
        </li>
      </ul>
      <SectionPager
        :page="commentsPage"
        :last-page="comments.data.value?.meta.last_page ?? 1"
        @change="commentsPage = $event"
      />
    </section>
    <section>
      <h2 class="mb-4 text-lg font-semibold">Attachments</h2>
      <div v-if="canCollaborate" class="mb-5 space-y-2">
        <label for="request-attachment" class="text-sm font-medium">Upload attachment</label
        ><input
          id="request-attachment"
          type="file"
          accept=".pdf,.txt,.csv,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx"
          class="block w-full text-sm"
          @change="selectFile"
        />
        <p v-if="file" class="text-sm text-muted-foreground">
          {{ file.name }} · {{ formatFileSize(file.size) }}
        </p>
        <p v-if="fileError" class="text-sm text-destructive">{{ fileError }}</p>
        <Button :disabled="upload.isPending.value" @click="submitFile">{{
          upload.isPending.value ? 'Uploading…' : 'Upload attachment'
        }}</Button>
      </div>
      <p v-if="sectionError" class="mb-3 text-sm text-destructive">{{ sectionError }}</p>
      <div v-if="attachments.isError.value" class="text-sm text-destructive">
        Unable to load attachments.
        <Button variant="link" class="h-auto p-0" @click="attachments.refetch()">Retry</Button>
      </div>
      <ul v-else class="space-y-3">
        <li
          v-for="attachment in attachments.data.value?.data ?? []"
          :key="attachment.id"
          class="flex flex-col gap-2 rounded-md border p-4 sm:flex-row sm:items-center"
        >
          <div class="min-w-0 flex-1">
            <p class="break-all font-medium">{{ attachment.original_name }}</p>
            <p class="text-xs text-muted-foreground">
              {{ formatFileSize(attachment.size_bytes) }} · {{ attachment.uploader.name }}
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            :aria-label="`Download ${attachment.original_name}`"
            @click="download(attachment.id, attachment.original_name)"
            >Download</Button
          >
        </li>
        <li
          v-if="!attachments.isPending.value && !attachments.data.value?.data.length"
          class="text-sm text-muted-foreground"
        >
          No attachments yet.
        </li>
      </ul>
      <SectionPager
        :page="attachmentsPage"
        :last-page="attachments.data.value?.meta.last_page ?? 1"
        @change="attachmentsPage = $event"
      />
    </section>
    <section>
      <h2 class="mb-4 text-lg font-semibold">Activity</h2>
      <div v-if="activity.isError.value" class="text-sm text-destructive">
        Unable to load activity.
        <Button variant="link" class="h-auto p-0" @click="activity.refetch()">Retry</Button>
      </div>
      <ol v-else class="space-y-3">
        <li
          v-for="entry in activity.data.value?.data ?? []"
          :key="entry.id"
          class="border-l-2 pl-4"
        >
          <p class="text-sm">{{ activityText(entry) }}</p>
          <time class="text-xs text-muted-foreground">{{
            new Date(entry.created_at).toLocaleString()
          }}</time>
        </li>
      </ol>
      <SectionPager
        :page="activityPage"
        :last-page="activity.data.value?.meta.last_page ?? 1"
        @change="activityPage = $event"
      />
    </section>
  </div>
</template>

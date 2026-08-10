<script setup lang="ts">
import type { RuntimeApproval } from '../types/request'
import { approvalStatusLabels } from '../requestStatus'
defineProps<{ approvals: RuntimeApproval[] }>()
</script>
<template>
  <ol v-if="approvals.length" class="space-y-3">
    <li
      v-for="approval in [...approvals].sort((a, b) => a.position - b.position)"
      :key="approval.id"
      class="flex gap-3 rounded-md border p-4"
    >
      <span class="font-medium">{{ approval.position }}</span>
      <div>
        <p class="font-medium">{{ approval.workflow_step.name }}</p>
        <p class="text-sm text-muted-foreground">
          {{ approvalStatusLabels[approval.status]
          }}<span v-if="approval.assignees.length">
            · {{ approval.assignees.map((item) => item.name).join(', ') }}</span
          >
        </p>
      </div>
    </li>
  </ol>
  <p v-else class="text-sm text-muted-foreground">
    No approval steps were materialized for this request.
  </p>
</template>

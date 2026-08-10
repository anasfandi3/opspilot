<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import type { ApprovalStatus } from '@/features/requests/types/request'
import { approvalStatusLabels } from '@/features/requests/requestStatus'
defineProps<{ status: ApprovalStatus }>()
</script>
<template>
  <Badge
    :variant="
      status === 'pending' ? 'default' : status === 'rejected' ? 'destructive' : 'secondary'
    "
    >{{ approvalStatusLabels[status] }}</Badge
  >
</template>

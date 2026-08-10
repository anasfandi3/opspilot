<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import type { RequestStatus } from '../types/request'
import { requestStatusLabels, requestStatusVariant } from '../requestStatus'
defineProps<{ status: RequestStatus }>()
</script>
<template>
  <Badge :variant="requestStatusVariant(status)">{{ requestStatusLabels[status] }}</Badge>
</template>

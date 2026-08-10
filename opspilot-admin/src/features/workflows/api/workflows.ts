import { apiClient, type ApiEnvelope } from '@/lib/api/client'
import type { Workflow, WorkflowInput, WorkflowStep, WorkflowStepInput } from '../types/workflow'
import type { RequestType } from '@/features/request-types/types/requestType'
import type { WorkflowListItem } from '../types/workflow'

const root = (workspaceId: number, requestTypeId: number) =>
  `/api/v1/workspaces/${workspaceId}/request-types/${requestTypeId}/workflows`
const workflowRoot = (workspaceId: number, requestTypeId: number, workflowId: number) =>
  `${root(workspaceId, requestTypeId)}/${workflowId}`

export const workflowsApi = {
  all: async (workspaceId: number, requestTypes: RequestType[]): Promise<WorkflowListItem[]> =>
    (
      await Promise.all(
        requestTypes.map(async (requestType) =>
          (await workflowsApi.list(workspaceId, requestType.id)).map((workflow) => ({
            ...workflow,
            requestType,
          })),
        ),
      )
    ).flat(),
  list: async (workspaceId: number, requestTypeId: number) =>
    (await apiClient.get<{ data: Workflow[] }>(root(workspaceId, requestTypeId))).data,
  detail: async (workspaceId: number, requestTypeId: number, workflowId: number) =>
    (
      await apiClient.get<ApiEnvelope<Workflow>>(
        workflowRoot(workspaceId, requestTypeId, workflowId),
      )
    ).data,
  create: async (workspaceId: number, requestTypeId: number, input: WorkflowInput) =>
    (
      await apiClient.post<ApiEnvelope<Workflow>>(root(workspaceId, requestTypeId), input, {
        csrf: true,
      })
    ).data,
  update: async (
    workspaceId: number,
    requestTypeId: number,
    workflowId: number,
    input: WorkflowInput,
  ) =>
    (
      await apiClient.patch<ApiEnvelope<Workflow>>(
        workflowRoot(workspaceId, requestTypeId, workflowId),
        input,
      )
    ).data,
  clone: async (workspaceId: number, requestTypeId: number, workflowId: number) =>
    (
      await apiClient.post<ApiEnvelope<Workflow>>(
        `${workflowRoot(workspaceId, requestTypeId, workflowId)}/clone`,
        undefined,
        { csrf: true },
      )
    ).data,
  publish: async (workspaceId: number, requestTypeId: number, workflowId: number) =>
    (
      await apiClient.post<ApiEnvelope<Workflow>>(
        `${workflowRoot(workspaceId, requestTypeId, workflowId)}/publish`,
        undefined,
        { csrf: true },
      )
    ).data,
  createStep: async (
    workspaceId: number,
    requestTypeId: number,
    workflowId: number,
    input: WorkflowStepInput,
  ) =>
    (
      await apiClient.post<ApiEnvelope<WorkflowStep>>(
        `${workflowRoot(workspaceId, requestTypeId, workflowId)}/steps`,
        input,
        { csrf: true },
      )
    ).data,
  updateStep: async (
    workspaceId: number,
    requestTypeId: number,
    workflowId: number,
    stepId: number,
    input: WorkflowStepInput,
  ) =>
    (
      await apiClient.patch<ApiEnvelope<WorkflowStep>>(
        `${workflowRoot(workspaceId, requestTypeId, workflowId)}/steps/${stepId}`,
        input,
      )
    ).data,
  deleteStep: (workspaceId: number, requestTypeId: number, workflowId: number, stepId: number) =>
    apiClient.delete(`${workflowRoot(workspaceId, requestTypeId, workflowId)}/steps/${stepId}`),
  reorderSteps: async (
    workspaceId: number,
    requestTypeId: number,
    workflowId: number,
    stepIds: number[],
  ) =>
    (
      await apiClient.post<ApiEnvelope<WorkflowStep[]>>(
        `${workflowRoot(workspaceId, requestTypeId, workflowId)}/steps/reorder`,
        { step_ids: stepIds },
        { csrf: true },
      )
    ).data,
}

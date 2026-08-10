import { ApiError } from '@/lib/api/errors'
import { workflowsApi } from './api/workflows'
import {
  defaultConditionValue,
  isBackendDatetime,
  normalizeStringSet,
  operatorsForField,
  validateStringSet,
} from './conditions'
import type { RequestType, RequestTypeField } from '@/features/request-types/types/requestType'
import type { WorkspaceMember } from '@/features/members/types/member'
import type {
  DraftCondition,
  DraftWorkflowStep,
  Workflow,
  WorkflowFormModel,
  WorkflowInput,
  WorkflowStepInput,
} from './types/workflow'

const uid = () => crypto.randomUUID()
export function createDraftCondition(field?: RequestTypeField): DraftCondition {
  const operator = field ? operatorsForField(field)[0]! : ''
  return {
    clientId: uid(),
    field_id: field?.id ?? null,
    operator,
    value: field && operator ? defaultConditionValue(field, operator) : null,
  }
}
export function createDraftStep(): DraftWorkflowStep {
  return {
    clientId: uid(),
    name: '',
    approver_type: 'role',
    approver_role: 'approver',
    approver_user_id: null,
    condition_logic: 'all',
    conditions: [],
  }
}
export function hydrateWorkflow(
  workflow?: Workflow,
  requestTypeId: number | null = null,
): WorkflowFormModel {
  return {
    request_type_id: requestTypeId,
    name: workflow?.name ?? '',
    description: workflow?.description ?? '',
    steps: [...(workflow?.steps ?? [])]
      .sort((a, b) => a.position - b.position)
      .map((step) => ({
        clientId: uid(),
        id: step.id,
        name: step.name,
        approver_type: step.approver_type,
        approver_role: step.approver_role,
        approver_user_id: step.approver_user?.id ?? null,
        condition_logic: step.condition_logic,
        conditions: [...step.conditions]
          .sort((a, b) => a.position - b.position)
          .map((condition) => ({
            clientId: uid(),
            field_id: condition.field.id,
            operator: condition.operator,
            value: JSON.parse(JSON.stringify(condition.value)),
          })),
      })),
  }
}
export const serializeWorkflow = (form: WorkflowFormModel): WorkflowInput => ({
  name: form.name.trim(),
  description: form.description.trim() || null,
})
export function serializeStep(step: DraftWorkflowStep): WorkflowStepInput {
  return {
    name: step.name.trim(),
    approver_type: step.approver_type,
    approver_role: step.approver_type === 'role' ? step.approver_role : null,
    approver_user_id: step.approver_type === 'user' ? step.approver_user_id : null,
    condition_logic: step.condition_logic,
    conditions: step.conditions.map((condition) => ({
      field_id: condition.field_id!,
      operator: condition.operator as WorkflowStepInput['conditions'][number]['operator'],
      value: Array.isArray(condition.value)
        ? normalizeStringSet(condition.value)
        : condition.value!,
    })),
  }
}
export function moveStep(steps: DraftWorkflowStep[], from: number, to: number) {
  if (to < 0 || to >= steps.length) return
  const [step] = steps.splice(from, 1)
  if (step) steps.splice(to, 0, step)
}
export function normalizeApprover(
  step: DraftWorkflowStep,
  type: DraftWorkflowStep['approver_type'],
) {
  return {
    ...step,
    approver_type: type,
    approver_role: type === 'role' ? ('approver' as const) : null,
    approver_user_id: type === 'user' ? null : null,
  }
}
export function specificApproverOptions(members: WorkspaceMember[]) {
  return members
}

export function specificApproverAvailability(hasMemberListAccess: boolean) {
  return hasMemberListAccess
    ? 'Approval eligibility is validated when the workflow is saved.'
    : 'Member list unavailable because you do not have permission to view workspace members. Role-based approvers remain available.'
}
export function validateWorkflowForm(form: WorkflowFormModel, requestType?: RequestType) {
  const errors: Record<string, string[]> = {}
  if (!form.request_type_id) errors.request_type_id = ['Select a request type.']
  if (!form.name.trim()) errors.name = ['Workflow name is required.']
  form.steps.forEach((step, index) => {
    if (!step.name.trim()) errors[`steps.${index}.name`] = ['Step name is required.']
    if (step.approver_type === 'role' && !step.approver_role)
      errors[`steps.${index}.approver_role`] = ['Select an approver role.']
    if (step.approver_type === 'user' && !step.approver_user_id)
      errors[`steps.${index}.approver_user_id`] = ['Select an approver.']
    step.conditions.forEach((condition, conditionIndex) => {
      const prefix = `steps.${index}.conditions.${conditionIndex}`
      const field = requestType?.fields.find((item) => item.id === condition.field_id)
      if (!condition.field_id) errors[`${prefix}.field_id`] = ['Select a field.']
      if (!condition.operator) errors[`${prefix}.operator`] = ['Select an operator.']
      if (
        condition.value === null ||
        condition.value === '' ||
        (Array.isArray(condition.value) && !condition.value.length)
      )
        errors[`${prefix}.value`] = ['Enter a condition value.']
      else if (Array.isArray(condition.value)) {
        const setError = validateStringSet(condition.value)
        if (setError) errors[`${prefix}.value`] = [setError]
      } else if (
        field &&
        (field.type === 'number' || field.type === 'decimal') &&
        (typeof condition.value !== 'number' || !Number.isFinite(condition.value))
      )
        errors[`${prefix}.value`] = ['Enter a valid number.']
      else if (field?.type === 'datetime' && !isBackendDatetime(condition.value))
        errors[`${prefix}.value`] = ['Enter a valid date and time.']
    })
  })
  return errors
}
export const canEditWorkflow = (workflow: Workflow) => workflow.status === 'draft'
export const canPublishWorkflow = (workflow: Workflow) =>
  workflow.status === 'draft' && workflow.steps.length > 0
export const canApplyWorkflowResult = (captured: number, current: number) => captured === current

export class PartialWorkflowSaveError extends Error {
  constructor(
    public workflow: Workflow,
    public cause: unknown,
    public created: boolean,
  ) {
    super(
      created
        ? 'The workflow was created, but not all steps were saved. The editor was refreshed.'
        : 'Some changes were saved before another change failed. The editor was refreshed to the server state.',
    )
  }
}
export async function persistWorkflowForm(
  workspaceId: number,
  form: WorkflowFormModel,
  original?: Workflow,
) {
  const requestTypeId = form.request_type_id!
  const workflow = original
    ? await workflowsApi.update(workspaceId, requestTypeId, original.id, serializeWorkflow(form))
    : await workflowsApi.create(workspaceId, requestTypeId, serializeWorkflow(form))
  try {
    const retained = new Set(form.steps.flatMap((step) => (step.id ? [step.id] : [])))
    const savedIds: number[] = []
    for (const step of form.steps) {
      const saved = step.id
        ? await workflowsApi.updateStep(
            workspaceId,
            requestTypeId,
            workflow.id,
            step.id,
            serializeStep(step),
          )
        : await workflowsApi.createStep(
            workspaceId,
            requestTypeId,
            workflow.id,
            serializeStep(step),
          )
      savedIds.push(saved.id)
    }
    if (original)
      for (const step of original.steps)
        if (!retained.has(step.id))
          await workflowsApi.deleteStep(workspaceId, requestTypeId, workflow.id, step.id)
    if (savedIds.length)
      await workflowsApi.reorderSteps(workspaceId, requestTypeId, workflow.id, savedIds)
    return await workflowsApi.detail(workspaceId, requestTypeId, workflow.id)
  } catch (error) {
    const authoritative = await workflowsApi.detail(workspaceId, requestTypeId, workflow.id)
    throw new PartialWorkflowSaveError(authoritative, error, !original)
  }
}
export function requestTypeForWorkflow(types: RequestType[], requestTypeId: number | null) {
  return types.find((type) => type.id === requestTypeId)
}
export function apiFieldErrors(error: unknown) {
  return error instanceof ApiError ? error.fieldErrors : {}
}

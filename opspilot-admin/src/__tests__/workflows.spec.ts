import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api/errors'
import { workflowsApi } from '@/features/workflows/api/workflows'
import { workflowKeys } from '@/features/workflows/queries/workflowKeys'
import {
  addStringSetValue,
  datetimeEditorValue,
  defaultConditionValue,
  formatCondition,
  isBackendDatetime,
  normalizeDatetimeEditorInput,
  normalizeCondition,
  normalizeNumericConditionInput,
  normalizeStringSet,
  operatorsForField,
  removeStringSetValue,
  updateStringSetValue,
  validateStringSet,
} from '@/features/workflows/conditions'
import {
  canApplyWorkflowResult,
  canEditWorkflow,
  canPublishWorkflow,
  createDraftStep,
  hydrateWorkflow,
  moveStep,
  normalizeApprover,
  PartialWorkflowSaveError,
  persistWorkflowForm,
  serializeStep,
  serializeWorkflow,
  specificApproverAvailability,
  specificApproverOptions,
  validateWorkflowForm,
} from '@/features/workflows/workflowForm'
import {
  resetWorkflowWorkspaceForm,
  workflowWorkspaceDestination,
} from '@/features/workflows/workspaceBehavior'
import type { RequestType } from '@/features/request-types/types/requestType'
import type { Workflow } from '@/features/workflows/types/workflow'
import type { WorkspaceMember } from '@/features/members/types/member'

const requestType: RequestType = {
  id: 4,
  name: 'Purchase',
  slug: 'purchase',
  description: null,
  is_active: true,
  creator: { id: 1, name: 'Owner' },
  created_at: '',
  updated_at: '',
  fields: [
    {
      id: 10,
      key: 'amount',
      label: 'Amount',
      type: 'number',
      description: null,
      is_required: true,
      position: 1,
      config: null,
    },
    {
      id: 11,
      key: 'priority',
      label: 'Priority',
      type: 'select',
      description: null,
      is_required: true,
      position: 2,
      config: {
        options: [
          { value: 'high', label: 'High priority' },
          { value: 'low', label: 'Low priority' },
        ],
      },
    },
    {
      id: 12,
      key: 'urgent',
      label: 'Urgent',
      type: 'boolean',
      description: null,
      is_required: false,
      position: 3,
      config: null,
    },
  ],
}
const workflow: Workflow = {
  id: 7,
  name: 'Approval',
  description: 'Review purchases',
  version: 2,
  status: 'draft',
  published_at: null,
  creator: { id: 1, name: 'Owner' },
  created_at: '',
  updated_at: '',
  steps: [
    {
      id: 21,
      name: 'Finance',
      position: 2,
      approver_type: 'user',
      approver_role: null,
      approver_user: { id: 3, name: 'Finance', email: 'finance@test' },
      condition_logic: 'any',
      conditions: [],
    },
    {
      id: 20,
      name: 'Manager',
      position: 1,
      approver_type: 'role',
      approver_role: 'admin',
      approver_user: null,
      condition_logic: 'all',
      conditions: [
        {
          id: 30,
          field: { id: 11, key: 'priority', label: 'Priority', type: 'select' },
          operator: 'equals',
          value: 'high',
          position: 1,
        },
      ],
    },
  ],
}

describe('workflow builder foundation', () => {
  afterEach(() => vi.restoreAllMocks())
  it('isolates workspace, request type, workflow, and version-resource query identities', () => {
    expect(workflowKeys.list(1)).toEqual(['workspace', 1, 'workflows', 'list'])
    expect(workflowKeys.detail(1, 4, 7)).not.toEqual(workflowKeys.detail(2, 4, 7))
    expect(workflowKeys.detail(1, 4, 7)).not.toEqual(workflowKeys.detail(1, 5, 7))
  })
  it('hydrates ordered steps and serializes exact endpoint payloads', () => {
    const form = hydrateWorkflow(workflow, requestType.id)
    expect(form.steps.map((step) => step.id)).toEqual([20, 21])
    expect(serializeWorkflow(form)).toEqual({ name: 'Approval', description: 'Review purchases' })
    expect(serializeStep(form.steps[0]!)).toEqual({
      name: 'Manager',
      approver_type: 'role',
      approver_role: 'admin',
      approver_user_id: null,
      condition_logic: 'all',
      conditions: [{ field_id: 11, operator: 'equals', value: 'high' }],
    })
  })
  it('adds, removes, and reorders steps deterministically', () => {
    const steps = [createDraftStep(), createDraftStep(), createDraftStep()]
    steps[0]!.name = 'One'
    steps[1]!.name = 'Two'
    steps[2]!.name = 'Three'
    moveStep(steps, 2, 0)
    expect(steps.map((step) => step.name)).toEqual(['Three', 'One', 'Two'])
    steps.splice(1, 1)
    expect(steps.map((step) => step.name)).toEqual(['Three', 'Two'])
  })
  it('supports only backend assignee types and clears incompatible values', () => {
    const role = { ...createDraftStep(), approver_role: 'owner' as const }
    const user = normalizeApprover(role, 'user')
    expect(user).toMatchObject({
      approver_type: 'user',
      approver_role: null,
      approver_user_id: null,
    })
    expect(normalizeApprover({ ...user, approver_user_id: 9 }, 'role')).toMatchObject({
      approver_type: 'role',
      approver_role: 'approver',
      approver_user_id: null,
    })
  })
  it('does not infer specific-user approval eligibility from workspace role names', () => {
    const members: WorkspaceMember[] = [
      {
        id: 1,
        name: 'Requester',
        email: 'requester@test',
        role: 'requester',
        roles: ['requester'],
        joined_at: '',
      },
      {
        id: 2,
        name: 'Auditor',
        email: 'auditor@test',
        role: 'auditor',
        roles: ['auditor'],
        joined_at: '',
      },
      {
        id: 3,
        name: 'Approver',
        email: 'approver@test',
        role: 'approver',
        roles: ['approver'],
        joined_at: '',
      },
    ]
    expect(specificApproverOptions(members)).toEqual(members)
    expect(specificApproverOptions(members).map((member) => member.role)).toEqual([
      'requester',
      'auditor',
      'approver',
    ])
    expect(specificApproverAvailability(true)).toContain('validated when the workflow is saved')
  })
  it('provides a clear selector state when members.view data is unavailable', () => {
    expect(specificApproverAvailability(false)).toContain('Member list unavailable')
    expect(specificApproverAvailability(false)).toContain('Role-based approvers remain available')
  })
  it('uses the exact backend field/operator compatibility matrix', () => {
    expect(operatorsForField(requestType.fields[0])).toContain('greater_than_or_equal')
    expect(operatorsForField(requestType.fields[1])).toEqual([
      'equals',
      'not_equals',
      'in',
      'not_in',
    ])
    expect(operatorsForField(requestType.fields[2])).toEqual(['equals', 'not_equals'])
  })
  it('normalizes stale field/operator/value state when the field changes', () => {
    const normalized = normalizeCondition(
      { clientId: 'x', field_id: 10, operator: 'greater_than', value: 100 },
      requestType.fields[1]!,
    )
    expect(normalized).toEqual({ clientId: 'x', field_id: 11, operator: 'equals', value: 'high' })
    expect(defaultConditionValue(requestType.fields[2]!, 'equals')).toBe(true)
  })
  it('serializes set, numeric, date, and boolean values without string coercion', () => {
    const step = createDraftStep()
    step.name = 'Typed'
    step.conditions = [
      { clientId: '1', field_id: 11, operator: 'in', value: ['high', 'low'] },
      { clientId: '2', field_id: 10, operator: 'greater_than', value: 100 },
      { clientId: '3', field_id: 12, operator: 'equals', value: false },
    ]
    expect(serializeStep(step).conditions.map((condition) => condition.value)).toEqual([
      ['high', 'low'],
      100,
      false,
    ])
  })
  it('normalizes numeric editor values without retaining strings or coercing empty input', () => {
    expect(normalizeNumericConditionInput('100')).toBe(100)
    expect(normalizeNumericConditionInput('10.5')).toBe(10.5)
    expect(normalizeNumericConditionInput('')).toBeNull()
    expect(normalizeNumericConditionInput('not-a-number')).toBeNull()
    expect(normalizeNumericConditionInput(Number.POSITIVE_INFINITY)).toBeNull()
  })
  it('adds, edits, removes, validates, and serializes ordered free-string sets', () => {
    let values = addStringSetValue([])
    values = updateStringSetValue(values, 0, ' finance ')
    values = addStringSetValue(values)
    values = updateStringSetValue(values, 1, 'operations')
    expect(validateStringSet(values)).toBe('')
    expect(normalizeStringSet(values)).toEqual(['finance', 'operations'])
    expect(validateStringSet(['finance', ' finance '])).toBe('Values must be unique.')
    expect(validateStringSet([''])).toBe('Values cannot be empty.')
    expect(removeStringSetValue(values, 0)).toEqual(['operations'])
    const step = createDraftStep()
    step.name = 'Routing'
    step.conditions = [
      { clientId: 'text', field_id: 20, operator: 'in', value: values },
      {
        clientId: 'email',
        field_id: 21,
        operator: 'not_in',
        value: [' blocked@example.com ', 'external@example.com'],
      },
    ]
    expect(serializeStep(step).conditions.map((condition) => condition.value)).toEqual([
      ['finance', 'operations'],
      ['blocked@example.com', 'external@example.com'],
    ])
  })
  it('round-trips backend datetimes through the local editor while retaining the offset', () => {
    const backend = '2026-08-10T15:30:00+03:00'
    const editor = datetimeEditorValue(backend)
    expect(editor).toBe('2026-08-10T15:30')
    expect(normalizeDatetimeEditorInput(editor, backend)).toBe(backend)
    expect(isBackendDatetime(backend)).toBe(true)
    expect(normalizeDatetimeEditorInput('', backend)).toBeNull()
    expect(normalizeDatetimeEditorInput('invalid', backend)).toBeNull()
  })
  it('formats conditions with field and option labels and safe values', () => {
    expect(formatCondition(workflow.steps[1]!.conditions[0]!, requestType.fields)).toBe(
      'Priority is equal to High priority',
    )
    expect(
      formatCondition(
        { ...workflow.steps[1]!.conditions[0]!, operator: 'in', value: ['high', 'low'] },
        requestType.fields,
      ),
    ).toBe('Priority is one of High priority, Low priority')
  })
  it('maps obvious nested structural validation paths', () => {
    const form = hydrateWorkflow()
    form.name = 'Test'
    form.request_type_id = 4
    form.steps = [createDraftStep()]
    form.steps[0]!.conditions = [{ clientId: 'x', field_id: null, operator: '', value: null }]
    const errors = validateWorkflowForm(form)
    expect(errors['steps.0.name']).toBeDefined()
    expect(errors['steps.0.conditions.0.field_id']).toBeDefined()
    expect(errors['steps.0.conditions.0.value']).toBeDefined()
  })
  it('keeps published versions immutable and gates publish to non-empty drafts', () => {
    expect(canEditWorkflow(workflow)).toBe(true)
    expect(canPublishWorkflow(workflow)).toBe(true)
    expect(canEditWorkflow({ ...workflow, status: 'active' })).toBe(false)
    expect(canPublishWorkflow({ ...workflow, steps: [] })).toBe(false)
  })
  it('resets old tenant drafts and routes resource pages to the safe list', () => {
    expect(workflowWorkspaceDestination('workflows')).toBeNull()
    expect(workflowWorkspaceDestination('workflows-edit')).toBe('/workflows')
    expect(resetWorkflowWorkspaceForm().form).toEqual(hydrateWorkflow())
  })
  it('rejects late mutation UI application across workspace boundaries', () => {
    expect(canApplyWorkflowResult(1, 1)).toBe(true)
    expect(canApplyWorkflowResult(1, 2)).toBe(false)
  })
  it('recovers authoritative workflow state after a partial multi-request save', async () => {
    const form = hydrateWorkflow()
    form.request_type_id = 4
    form.name = 'Created'
    const first = createDraftStep()
    first.name = 'First'
    const second = createDraftStep()
    second.name = 'Second'
    form.steps = [first, second]
    const created = { ...workflow, id: 50, name: 'Created', steps: [] }
    const authoritative = {
      ...created,
      steps: [{ ...workflow.steps[1]!, id: 60, name: 'First', position: 1 }],
    }
    vi.spyOn(workflowsApi, 'create').mockResolvedValue(created)
    vi.spyOn(workflowsApi, 'createStep')
      .mockResolvedValueOnce(authoritative.steps[0]!)
      .mockRejectedValueOnce(new ApiError('validation', 'Second step invalid', 422))
    vi.spyOn(workflowsApi, 'detail').mockResolvedValue(authoritative)
    const error = await persistWorkflowForm(1, form).catch((caught) => caught)
    expect(error).toBeInstanceOf(PartialWorkflowSaveError)
    expect(error).toMatchObject({ workflow: authoritative, created: true })
    expect(workflowsApi.detail).toHaveBeenCalledWith(1, 4, 50)
  })
  it('updates retained steps before attempting destructive deletion', async () => {
    const form = hydrateWorkflow(workflow, 4)
    form.steps = [form.steps[0]!]
    vi.spyOn(workflowsApi, 'update').mockResolvedValue(workflow)
    const update = vi.spyOn(workflowsApi, 'updateStep').mockResolvedValue(workflow.steps[1]!)
    const remove = vi
      .spyOn(workflowsApi, 'deleteStep')
      .mockRejectedValue(new ApiError('validation', 'Blocked', 422))
    vi.spyOn(workflowsApi, 'detail').mockResolvedValue(workflow)
    await persistWorkflowForm(1, form, workflow).catch(() => undefined)
    expect(update.mock.invocationCallOrder[0]).toBeLessThan(remove.mock.invocationCallOrder[0]!)
  })
})

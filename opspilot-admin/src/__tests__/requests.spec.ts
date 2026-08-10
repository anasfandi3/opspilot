import { describe, expect, it } from 'vitest'
import type { RequestTypeField } from '@/features/request-types/types/requestType'
import { requestKeys } from '@/features/requests/queries/requestKeys'
import {
  formatValue,
  initializeValues,
  mapPayloadErrors,
  serializeValues,
  validateValues,
} from '@/features/requests/fieldValues'
import { approvalStatusLabels, requestStatusLabels } from '@/features/requests/requestStatus'
import {
  canApplyRequestResult,
  requestWorkspaceDestination,
} from '@/features/requests/workspaceBehavior'
import {
  canCancelRequest,
  canCollaborateOnRequest,
  canEditRequest,
  canSubmitRequest,
  PartialRequestSubmitError,
  persistRequest,
} from '@/features/requests/requestActions'
import { numericInputValue, rangeConfig } from '@/features/requests/fieldConfig'
import type { RequestDetail } from '@/features/requests/types/request'

const field = (
  key: string,
  type: RequestTypeField['type'],
  config: RequestTypeField['config'] = null,
  required = false,
): RequestTypeField => ({
  id: Math.random(),
  key,
  label: key,
  type,
  description: null,
  is_required: required,
  position: 1,
  config,
})
const fields = [
  field('title', 'text', { min_length: 3, max_length: 10 }, true),
  field('count', 'number', { min: 1, max: 5 }),
  field('cost', 'decimal'),
  field('urgent', 'boolean'),
  field('when', 'date'),
  field('at', 'datetime'),
  field('choice', 'select', { options: [{ value: 'a', label: 'Alpha' }] }),
  field('many', 'multiselect', { options: [{ value: 'a', label: 'Alpha' }] }),
  field('email', 'email'),
  field('url', 'url'),
]

describe('requests foundation', () => {
  it('isolates workspace list, detail, and filter query keys', () => {
    expect(requestKeys.list(1, { page: 1, perPage: 20 })).not.toEqual(
      requestKeys.list(2, { page: 1, perPage: 20 }),
    )
    expect(requestKeys.detail(1, 9)).not.toEqual(requestKeys.list(1, { page: 1, perPage: 20 }))
  })

  it('initializes and hydrates only schema fields with typed defaults', () => {
    expect(initializeValues(fields, { title: 'Saved', count: 2, obsolete: 'x' })).toMatchObject({
      title: 'Saved',
      count: 2,
      urgent: false,
      many: [],
    })
    expect(initializeValues(fields, { obsolete: 'x' })).not.toHaveProperty('obsolete')
  })

  it('serializes exact typed aggregate payload and omits empty values', () => {
    expect(
      serializeValues(fields, {
        title: 'Office',
        count: 2,
        cost: 2.5,
        urgent: true,
        when: '2026-08-10',
        at: '2026-08-10T12:00:00+03:00',
        choice: 'a',
        many: ['a'],
        email: null,
        url: '',
      }),
    ).toEqual({
      title: 'Office',
      count: 2,
      cost: 2.5,
      urgent: true,
      when: '2026-08-10',
      at: '2026-08-10T12:00:00+03:00',
      choice: 'a',
      many: ['a'],
    })
  })

  it('validates required, ranges, lengths, options, email, url, and datetime', () => {
    const errors = validateValues(
      fields,
      { title: '', count: 9, choice: 'bad', many: ['bad'], email: 'bad', url: 'bad', at: 'bad' },
      true,
    )
    expect(errors).toMatchObject({
      title: expect.any(Array),
      count: expect.any(Array),
      choice: expect.any(Array),
      many: expect.any(Array),
      email: expect.any(Array),
      url: expect.any(Array),
      at: expect.any(Array),
    })
  })

  it('formats typed values without recomputing workflow behavior', () => {
    expect(formatValue(fields[3], true)).toBe('Yes')
    expect(formatValue(fields[6], 'a')).toBe('Alpha')
    expect(formatValue(fields[7], ['a'])).toBe('Alpha')
    expect(formatValue(fields[1], 1200)).toContain('1,200')
    expect(approvalStatusLabels.pending).toBe('Pending')
    expect(requestStatusLabels.submitted).toBe('Submitted')
  })

  it('maps Laravel payload errors to dynamic fields', () => {
    expect(mapPayloadErrors({ 'payload.title': ['Required'], request_type_id: ['Bad'] })).toEqual({
      title: ['Required'],
    })
  })

  it('rejects old-workspace mutation results and resets record routes', () => {
    expect(canApplyRequestResult(1, 2)).toBe(false)
    expect(canApplyRequestResult(2, 2)).toBe(true)
    expect(requestWorkspaceDestination('requests-edit')).toBe('/requests')
    expect(requestWorkspaceDestination('requests')).toBeNull()
  })

  it('preserves zero numeric bounds and normalizes numeric editor values', () => {
    expect(rangeConfig({ min: 0, max: 0 })).toEqual({ min: 0, max: 0 })
    expect(numericInputValue('0', 'number')).toBe(0)
    expect(numericInputValue('2.5', 'decimal')).toBe(2.5)
    expect(numericInputValue('', 'number')).toBeNull()
    expect(numericInputValue('2.5', 'number')).toBeNull()
    expect(numericInputValue('Infinity', 'decimal')).toBeNull()
  })

  it('presents edit, submit, and cancel actions only to the owning user', () => {
    const request = requestDetail({
      status: 'draft',
      creator: { id: 7, name: 'Owner', email: 'o@example.test' },
    })
    const can = () => true
    expect(canEditRequest(request, 7, can)).toBe(true)
    expect(canSubmitRequest(request, 7, can)).toBe(true)
    expect(canCancelRequest(request, 7, can)).toBe(true)
    expect(canEditRequest(request, 8, can)).toBe(false)
    expect(canSubmitRequest(request, 8, can)).toBe(false)
    expect(canCancelRequest(request, 8, can)).toBe(false)
    expect(canCancelRequest({ ...request, status: 'approved' }, 7, can)).toBe(false)
    expect(canEditRequest({ ...request, status: 'submitted' }, 7, can)).toBe(false)
    expect(canEditRequest({ ...request, status: 'cancelled' }, 7, can)).toBe(false)
  })

  it('does not let update permission override edit-form ownership', () => {
    const ownersDraft = requestDetail({
      creator: { id: 7, name: 'Owner', email: 'o@example.test' },
    })
    const canUpdate = (permission: string) => permission === 'requests.update_own'
    expect(canEditRequest(ownersDraft, 7, canUpdate)).toBe(true)
    expect(canEditRequest(ownersDraft, 8, canUpdate)).toBe(false)
  })

  it('mirrors collaboration policy inputs without inferring permissions from roles', () => {
    const request = requestDetail({
      creator: { id: 7, name: 'Creator', email: 'creator@example.test' },
      approvals: [
        {
          id: 11,
          position: 1,
          status: 'approved',
          workflow_step: { id: 5, name: 'Historical step' },
          approver_type: 'user',
          approver_role: null,
          assignees: [{ id: 8, name: 'Historical approver' }],
          decided_by: { id: 8, name: 'Historical approver' },
          activated_at: '2026-08-10T00:00:00Z',
          decided_at: '2026-08-10T01:00:00Z',
        },
      ],
    })
    const permits =
      (...allowed: string[]) =>
      (permission: string) =>
        allowed.includes(permission)

    expect(canCollaborateOnRequest(request, 7, 'requester', permits('requests.view_own'))).toBe(
      true,
    )
    expect(canCollaborateOnRequest(request, 7, 'requester', permits())).toBe(false)
    expect(
      canCollaborateOnRequest(request, 8, 'approver', permits('approvals.view_assigned')),
    ).toBe(true)
    expect(
      canCollaborateOnRequest(request, 9, 'approver', permits('approvals.view_assigned')),
    ).toBe(false)
    expect(canCollaborateOnRequest(request, 9, 'owner', permits('requests.view_all'))).toBe(true)
    expect(canCollaborateOnRequest(request, 9, 'admin', permits('requests.view_all'))).toBe(true)
    expect(canCollaborateOnRequest(request, 9, 'owner', permits())).toBe(false)
    expect(canCollaborateOnRequest(request, 9, 'admin', permits())).toBe(false)
    expect(canCollaborateOnRequest(request, 9, 'requester', permits('requests.view_all'))).toBe(
      false,
    )
    expect(canCollaborateOnRequest(request, 9, 'auditor', permits('requests.view_all'))).toBe(false)
    expect(canCollaborateOnRequest(request, null, 'owner', permits('requests.view_all'))).toBe(
      false,
    )
  })

  it('retains the persisted ID when create succeeds and submit fails', async () => {
    const saved = requestDetail({ id: 42, payload: { title: 'Persisted' } })
    let creates = 0
    const api = {
      create: async () => {
        creates += 1
        return saved
      },
      update: async () => saved,
      submit: async () => {
        throw new Error('Workflow unavailable')
      },
    }
    await expect(
      persistRequest(
        { workspaceId: 1, requestId: 0, requestTypeId: 3, payload: saved.payload, submit: true },
        api,
      ),
    ).rejects.toMatchObject({
      request: { id: 42, payload: { title: 'Persisted' } },
      message: expect.stringContaining('The draft was saved, but submission failed.'),
    })
    expect(creates).toBe(1)
  })

  it('retains authoritative updated payload when edit submission fails', async () => {
    const updated = requestDetail({ id: 12, payload: { title: 'Server value' } })
    const error = await persistRequest(
      { workspaceId: 1, requestId: 12, requestTypeId: 3, payload: updated.payload, submit: true },
      {
        create: async () => updated,
        update: async () => updated,
        submit: async () => {
          throw new Error('Rejected')
        },
      },
    ).catch((caught: unknown) => caught)
    expect(error).toBeInstanceOf(PartialRequestSubmitError)
    expect((error as PartialRequestSubmitError).request.payload).toEqual({
      title: 'Server value',
    })
  })
})

function requestDetail(overrides: Partial<RequestDetail> = {}): RequestDetail {
  return {
    id: 1,
    status: 'draft',
    payload: {},
    request_type: { id: 3, name: 'Type', slug: 'type' },
    workflow: null,
    creator: { id: 7, name: 'Owner', email: 'owner@example.test' },
    submitted_at: null,
    cancelled_at: null,
    resolved_at: null,
    created_at: '2026-08-10T00:00:00Z',
    updated_at: '2026-08-10T00:00:00Z',
    definition_snapshot: null,
    approvals: [],
    ...overrides,
  }
}

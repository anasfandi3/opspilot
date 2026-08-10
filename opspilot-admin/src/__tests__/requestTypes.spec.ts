import { afterEach, describe, expect, it, vi } from 'vitest'
import { dirtySnapshot, shouldAllowDirtyNavigation } from '@/composables/useDirtyState'
import { ApiError } from '@/lib/api/errors'
import {
  addOption,
  createDraftField,
  fieldError,
  fieldIdsForOrder,
  hydrateRequestType,
  moveField,
  normalizeFieldType,
  removeOption,
  serializeField,
  updateOption,
  validateOptions,
} from '@/features/request-types/fieldTypes'
import {
  canApplySaveResult,
  PartialRequestTypeSaveError,
  partialSaveMessage,
  persistRequestTypeForm,
  serializeRequestType,
  validateRequestTypeForm,
} from '@/features/request-types/requestTypeForm'
import { requestTypesApi } from '@/features/request-types/api/requestTypes'
import { requestTypeKeys } from '@/features/request-types/queries/requestTypeKeys'
import {
  requestTypeWorkspaceSwitchDestination,
  resetRequestTypeWorkspaceForm,
} from '@/features/request-types/workspaceBehavior'
import type { RequestType } from '@/features/request-types/types/requestType'

const response: RequestType = {
  id: 9,
  name: 'Purchase request',
  slug: 'purchase-request',
  description: 'Purchases',
  is_active: true,
  creator: { id: 1, name: 'Owner' },
  fields: [
    {
      id: 12,
      key: 'priority',
      label: 'Priority',
      type: 'select',
      description: null,
      is_required: true,
      position: 2,
      config: { options: [{ value: 'high', label: 'High' }] },
    },
    {
      id: 11,
      key: 'summary',
      label: 'Summary',
      type: 'text',
      description: 'Short summary',
      is_required: true,
      position: 1,
      config: { min_length: 3, max_length: 100 },
    },
  ],
  created_at: '',
  updated_at: '',
}

describe('request type foundation', () => {
  afterEach(() => vi.restoreAllMocks())

  it('isolates list and detail query keys by workspace', () => {
    expect(requestTypeKeys.list(1)).toEqual(['workspace', 1, 'request-types', 'list'])
    expect(requestTypeKeys.detail(1, 9)).not.toEqual(requestTypeKeys.detail(2, 9))
    expect(requestTypeKeys.detail(1, 9)).not.toEqual(requestTypeKeys.detail(1, 10))
  })

  it('normalizes incompatible config while preserving common field settings', () => {
    const field = {
      ...createDraftField('select'),
      key: 'priority',
      label: 'Priority',
      description: 'Choose one',
      is_required: true,
      config: { options: [{ value: 'high', label: 'High' }] },
    }
    const normalized = normalizeFieldType(field, 'number')
    expect(normalized.config).toBeNull()
    expect(normalized).toMatchObject({ key: 'priority', label: 'Priority', is_required: true })
  })

  it('moves fields deterministically and produces ordered persisted IDs', () => {
    const fields = [
      { ...createDraftField(), id: 1 },
      { ...createDraftField(), id: 2 },
      { ...createDraftField(), id: 3 },
    ]
    moveField(fields, 2, 0)
    expect(fieldIdsForOrder(fields)).toEqual([3, 1, 2])
    moveField(fields, 0, 1)
    expect(fieldIdsForOrder(fields)).toEqual([1, 3, 2])
  })

  it('adds, edits, removes, and validates select options', () => {
    let options = addOption([])
    options = updateOption(options, 0, 'value', 'finance')
    options = updateOption(options, 0, 'label', 'Finance')
    expect(validateOptions(options)).toBe('')
    options = addOption(options)
    options = updateOption(options, 1, 'value', 'finance')
    options = updateOption(options, 1, 'label', 'Duplicate')
    expect(validateOptions(options)).toBe('Option values must be unique.')
    expect(removeOption(options, 1)).toEqual([{ value: 'finance', label: 'Finance' }])
  })

  it('maps nested validation paths and clears cleanly on structural replacement', () => {
    const errors = { 'fields.1.config': ['Invalid options.'] }
    expect(fieldError(errors, 1, 'config')).toBe('Invalid options.')
    expect(fieldError({}, 1, 'config')).toBe('')
  })

  it('hydrates ordered backend fields and serializes the exact endpoint payload shape', () => {
    const form = hydrateRequestType(response)
    expect(form.fields.map((field) => field.id)).toEqual([11, 12])
    expect(serializeRequestType(form)).toEqual({
      name: 'Purchase request',
      description: 'Purchases',
      is_active: true,
    })
    expect(serializeField(form.fields[1]!)).toEqual({
      key: 'priority',
      label: 'Priority',
      type: 'select',
      description: null,
      is_required: true,
      config: { options: [{ value: 'high', label: 'High' }] },
    })
  })

  it('detects structural validation problems before persistence', () => {
    const form = hydrateRequestType()
    form.name = 'Type'
    form.fields = [createDraftField('select')]
    const errors = validateRequestTypeForm(form)
    expect(errors['fields.0.label']).toBeDefined()
    expect(errors['fields.0.key']).toBeDefined()
    expect(errors['fields.0.config']).toBeDefined()
  })

  it('keeps hydration clean, marks edits dirty, and resets after save', () => {
    const form = hydrateRequestType(response)
    let baseline = dirtySnapshot(form)
    expect(dirtySnapshot(form)).toBe(baseline)
    form.name = 'Updated'
    expect(dirtySnapshot(form)).not.toBe(baseline)
    baseline = dirtySnapshot(form)
    expect(dirtySnapshot(form)).toBe(baseline)
  })

  it('returns resource routes to the safe list after workspace switching', () => {
    expect(requestTypeWorkspaceSwitchDestination('request-types')).toBeNull()
    expect(requestTypeWorkspaceSwitchDestination('request-types-detail')).toBe('/request-types')
    expect(requestTypeWorkspaceSwitchDestination('request-types-edit')).toBe('/request-types')
    expect(requestTypeWorkspaceSwitchDestination('request-types-create')).toBe('/request-types')
  })

  it('guards ordinary dirty navigation but allows a trusted workspace discard', () => {
    const confirm = vi.fn<() => boolean>(() => false)
    expect(shouldAllowDirtyNavigation(true, false, confirm)).toBe(false)
    expect(confirm).toHaveBeenCalledOnce()
    confirm.mockClear()
    expect(shouldAllowDirtyNavigation(true, true, confirm)).toBe(true)
    expect(confirm).not.toHaveBeenCalled()
  })

  it('clears workspace-A draft and errors before entering workspace B', () => {
    const stale = hydrateRequestType(response)
    stale.name = 'Workspace A draft'
    const reset = resetRequestTypeWorkspaceForm()
    expect(reset.form).toEqual(hydrateRequestType())
    expect(reset.form.name).not.toBe(stale.name)
    expect(reset.errors).toEqual({})
    expect(reset.generalError).toBe('')
  })

  it('only applies save results to the workspace captured at invocation', () => {
    expect(canApplySaveResult(1, 1)).toBe(true)
    expect(canApplySaveResult(1, 2)).toBe(false)
  })

  it('recovers authoritative state when a later created field fails', async () => {
    const form = hydrateRequestType()
    form.name = 'New type'
    form.fields = [
      { ...createDraftField(), key: 'first', label: 'First' },
      { ...createDraftField(), key: 'second', label: 'Second' },
    ]
    const created = { ...response, id: 20, name: form.name, fields: [] }
    const authoritative = {
      ...created,
      fields: [{ ...response.fields[1]!, id: 101, key: 'first', label: 'First', position: 1 }],
    }
    vi.spyOn(requestTypesApi, 'create').mockResolvedValue(created)
    vi.spyOn(requestTypesApi, 'createField')
      .mockResolvedValueOnce(authoritative.fields[0]!)
      .mockRejectedValueOnce(new ApiError('validation', 'Second field is invalid.', 422))
    vi.spyOn(requestTypesApi, 'detail').mockResolvedValue(authoritative)

    const error = await persistRequestTypeForm(1, form).catch((caught) => caught)
    expect(error).toBeInstanceOf(PartialRequestTypeSaveError)
    expect(error).toMatchObject({ requestType: authoritative, created: true })
    expect(partialSaveMessage(error)).toContain('not all field changes were saved')
    expect(partialSaveMessage(error)).toContain('Second field is invalid.')
    expect(requestTypesApi.detail).toHaveBeenCalledWith(1, 20)
  })

  it('retries from refreshed server IDs without recreating a saved field', async () => {
    const authoritative = {
      ...response,
      fields: [{ ...response.fields[1]!, id: 101, key: 'first', label: 'First', position: 1 }],
    }
    const refreshed = hydrateRequestType(authoritative)
    vi.spyOn(requestTypesApi, 'update').mockResolvedValue(authoritative)
    vi.spyOn(requestTypesApi, 'updateField').mockResolvedValue(authoritative.fields[0]!)
    const createField = vi.spyOn(requestTypesApi, 'createField')
    vi.spyOn(requestTypesApi, 'reorderFields').mockResolvedValue(authoritative.fields)
    vi.spyOn(requestTypesApi, 'detail').mockResolvedValue(authoritative)

    await expect(persistRequestTypeForm(1, refreshed, authoritative)).resolves.toEqual(
      authoritative,
    )
    expect(createField).not.toHaveBeenCalled()
    expect(requestTypesApi.updateField).toHaveBeenCalledWith(1, 9, 101, expect.any(Object))
  })

  it('refreshes retained fields when a deletion is rejected', async () => {
    const form = hydrateRequestType(response)
    form.fields = form.fields.filter((field) => field.id !== 12)
    vi.spyOn(requestTypesApi, 'update').mockResolvedValue(response)
    vi.spyOn(requestTypesApi, 'updateField').mockResolvedValue(response.fields[1]!)
    vi.spyOn(requestTypesApi, 'deleteField').mockRejectedValue(
      new ApiError('validation', 'Field is used by a workflow.', 422),
    )
    const reorderFields = vi.spyOn(requestTypesApi, 'reorderFields')
    vi.spyOn(requestTypesApi, 'detail').mockResolvedValue(response)

    const error = await persistRequestTypeForm(1, form, response).catch((caught) => caught)
    expect(error).toBeInstanceOf(PartialRequestTypeSaveError)
    expect(error.requestType.fields.map((field: { id: number }) => field.id)).toEqual([12, 11])
    expect(reorderFields).not.toHaveBeenCalled()
  })
})

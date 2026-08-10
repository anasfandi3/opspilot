import { ApiError } from '@/lib/api/errors'
import { requestTypesApi } from './api/requestTypes'
import { serializeField } from './fieldTypes'
import type { RequestType, RequestTypeFormModel, RequestTypeInput } from './types/requestType'

export class RequestTypeFieldSaveError extends Error {
  constructor(
    public index: number,
    public apiError: ApiError,
  ) {
    super(apiError.message)
    this.name = 'RequestTypeFieldSaveError'
  }
}

export class PartialRequestTypeSaveError extends Error {
  constructor(
    public requestType: RequestType,
    public cause: unknown,
    public created: boolean,
  ) {
    super(
      created
        ? 'The request type was created, but not all field changes were saved.'
        : 'Some changes were saved before another change failed. The form has been refreshed to the current server state.',
    )
    this.name = 'PartialRequestTypeSaveError'
  }
}

export function canApplySaveResult(capturedWorkspaceId: number, currentWorkspaceId: number) {
  return capturedWorkspaceId === currentWorkspaceId
}

export function partialSaveMessage(error: PartialRequestTypeSaveError) {
  const cause = error.cause instanceof Error ? error.cause.message : ''
  return cause && cause !== error.message ? `${error.message} ${cause}` : error.message
}

export function serializeRequestType(form: RequestTypeFormModel): RequestTypeInput {
  return {
    name: form.name.trim(),
    description: form.description.trim() || null,
    is_active: form.is_active,
  }
}

export function validateRequestTypeForm(form: RequestTypeFormModel) {
  const errors: Record<string, string[]> = {}
  if (!form.name.trim()) errors.name = ['Request type name is required.']
  const keys = new Set<string>()
  form.fields.forEach((field, index) => {
    if (!field.label.trim()) errors[`fields.${index}.label`] = ['Field label is required.']
    if (!field.key.trim()) errors[`fields.${index}.key`] = ['Field key is required.']
    else if (!/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/.test(field.key.trim()))
      errors[`fields.${index}.key`] = ['Use lowercase snake_case beginning with a letter.']
    else if (keys.has(field.key.trim()))
      errors[`fields.${index}.key`] = ['Field keys must be unique.']
    keys.add(field.key.trim())
    if (field.type === 'select' || field.type === 'multiselect') {
      const options = (field.config as { options?: Array<{ value: string; label: string }> } | null)
        ?.options
      if (!options?.length) errors[`fields.${index}.config`] = ['Add at least one option.']
      else if (options.some((option) => !option.value.trim() || !option.label.trim()))
        errors[`fields.${index}.config`] = ['Option values and labels are required.']
      else if (new Set(options.map((option) => option.value.trim())).size !== options.length)
        errors[`fields.${index}.config`] = ['Option values must be unique.']
    }
  })
  return errors
}

export async function persistRequestTypeForm(
  workspaceId: number,
  form: RequestTypeFormModel,
  original?: RequestType,
) {
  const created = !original
  const requestType = original
    ? await requestTypesApi.update(workspaceId, original.id, serializeRequestType(form))
    : await requestTypesApi.create(workspaceId, serializeRequestType(form))
  const requestTypeId = requestType.id
  try {
    const retainedIds = new Set(form.fields.flatMap((field) => (field.id ? [field.id] : [])))
    const savedIds: number[] = []
    for (const [index, field] of form.fields.entries()) {
      const input = serializeField(field)
      try {
        if (field.id) {
          const saved = await requestTypesApi.updateField(workspaceId, requestTypeId, field.id, {
            label: input.label,
            description: input.description,
            is_required: input.is_required,
            config: input.config,
          })
          savedIds.push(saved.id)
        } else {
          const saved = await requestTypesApi.createField(workspaceId, requestTypeId, input)
          savedIds.push(saved.id)
        }
      } catch (error) {
        if (error instanceof ApiError) throw new RequestTypeFieldSaveError(index, error)
        throw error
      }
    }

    if (original) {
      for (const field of original.fields) {
        if (!retainedIds.has(field.id))
          await requestTypesApi.deleteField(workspaceId, requestTypeId, field.id)
      }
    }

    if (savedIds.length) await requestTypesApi.reorderFields(workspaceId, requestTypeId, savedIds)
    return await requestTypesApi.detail(workspaceId, requestTypeId)
  } catch (error) {
    const authoritative = await requestTypesApi.detail(workspaceId, requestTypeId)
    throw new PartialRequestTypeSaveError(authoritative, error, created)
  }
}

export function mapFieldSaveErrors(error: RequestTypeFieldSaveError) {
  return Object.fromEntries(
    Object.entries(error.apiError.fieldErrors).map(([path, messages]) => [
      `fields.${error.index}.${path}`,
      messages,
    ]),
  )
}

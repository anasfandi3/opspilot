import type { RequestDetail, RequestPayload, RequestSummary } from './types/request'

type PermissionCheck = (permission: string) => boolean

function isOwnedBy(request: RequestSummary, currentUserId?: number | null) {
  return currentUserId != null && request.creator.id === currentUserId
}

export function canEditRequest(
  request: RequestSummary,
  currentUserId: number | null | undefined,
  can: PermissionCheck,
) {
  return (
    request.status === 'draft' && isOwnedBy(request, currentUserId) && can('requests.update_own')
  )
}

export function canSubmitRequest(
  request: RequestSummary,
  currentUserId: number | null | undefined,
  can: PermissionCheck,
) {
  return request.status === 'draft' && isOwnedBy(request, currentUserId) && can('requests.submit')
}

export function canCancelRequest(
  request: RequestSummary,
  currentUserId: number | null | undefined,
  can: PermissionCheck,
) {
  return (
    (request.status === 'draft' || request.status === 'submitted') &&
    isOwnedBy(request, currentUserId) &&
    can('requests.cancel_own')
  )
}

export interface RequestPersistenceInput {
  workspaceId: number
  requestId: number
  requestTypeId: number
  payload: RequestPayload
  submit: boolean
}

export interface RequestPersistenceApi {
  create(
    workspaceId: number,
    requestTypeId: number,
    payload: RequestPayload,
  ): Promise<RequestDetail>
  update(workspaceId: number, requestId: number, payload: RequestPayload): Promise<RequestDetail>
  submit(workspaceId: number, requestId: number): Promise<RequestDetail>
}

export class PartialRequestSubmitError extends Error {
  constructor(
    public request: RequestDetail,
    public submitError: unknown,
  ) {
    const detail = submitError instanceof Error ? ` ${submitError.message}` : ''
    super(
      `The draft was saved, but submission failed. Review the request and try submitting again.${detail}`,
    )
    this.name = 'PartialRequestSubmitError'
  }
}

export async function persistRequest(input: RequestPersistenceInput, api: RequestPersistenceApi) {
  const saved = input.requestId
    ? await api.update(input.workspaceId, input.requestId, input.payload)
    : await api.create(input.workspaceId, input.requestTypeId, input.payload)
  if (!input.submit) return saved
  try {
    return await api.submit(input.workspaceId, saved.id)
  } catch (error) {
    throw new PartialRequestSubmitError(saved, error)
  }
}

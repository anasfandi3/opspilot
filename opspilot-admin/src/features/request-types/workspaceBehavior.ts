import { hydrateRequestType } from './fieldTypes'

export function requestTypeWorkspaceSwitchDestination(routeName: unknown) {
  return routeName === 'request-types' ? null : '/request-types'
}

export function resetRequestTypeWorkspaceForm() {
  return { form: hydrateRequestType(), errors: {}, generalError: '' }
}

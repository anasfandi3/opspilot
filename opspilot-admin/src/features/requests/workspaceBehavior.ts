export function requestWorkspaceDestination(routeName: unknown) {
  return routeName === 'requests' ? null : '/requests'
}
export function canApplyRequestResult(mutationWorkspaceId: number, currentWorkspaceId: number) {
  return mutationWorkspaceId === currentWorkspaceId
}

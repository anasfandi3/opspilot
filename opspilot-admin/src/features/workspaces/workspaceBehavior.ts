export interface WorkspaceUpdateInput {
  workspaceId: number
  name: string
}

export function workspaceUpdateInput(workspaceId: number, name: string): WorkspaceUpdateInput {
  return { workspaceId, name }
}

export function canApplyWorkspaceResult(sourceWorkspaceId: number, currentWorkspaceId: number) {
  return sourceWorkspaceId === currentWorkspaceId
}

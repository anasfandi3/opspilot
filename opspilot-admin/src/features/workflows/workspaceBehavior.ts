import { hydrateWorkflow } from './workflowForm'
export const workflowWorkspaceDestination = (routeName: unknown) =>
  routeName === 'workflows' ? null : '/workflows'
export const resetWorkflowWorkspaceForm = () => ({
  form: hydrateWorkflow(),
  errors: {},
  generalError: '',
})

import type {
  RequestFieldType,
  RequestType,
  RequestTypeField,
} from '@/features/request-types/types/requestType'

export type WorkflowStatus = 'draft' | 'active' | 'archived'
export type WorkflowApproverType = 'role' | 'user'
export type WorkflowApproverRole = 'owner' | 'admin' | 'approver'
export type WorkflowConditionLogic = 'all' | 'any'
export type WorkflowConditionOperator =
  | 'equals'
  | 'not_equals'
  | 'greater_than'
  | 'greater_than_or_equal'
  | 'less_than'
  | 'less_than_or_equal'
  | 'in'
  | 'not_in'
  | 'contains'
  | 'not_contains'
export type WorkflowConditionValue = string | number | boolean | string[]

export interface WorkflowCondition {
  id: number
  field: Pick<RequestTypeField, 'id' | 'key' | 'label' | 'type'>
  operator: WorkflowConditionOperator
  value: WorkflowConditionValue
  position: number
}
export interface WorkflowStep {
  id: number
  name: string
  position: number
  approver_type: WorkflowApproverType
  approver_role: WorkflowApproverRole | null
  approver_user: { id: number; name: string; email: string } | null
  condition_logic: WorkflowConditionLogic
  conditions: WorkflowCondition[]
}
export interface Workflow {
  id: number
  name: string
  description: string | null
  version: number
  status: WorkflowStatus
  published_at: string | null
  creator: { id: number; name: string }
  steps: WorkflowStep[]
  created_at: string
  updated_at: string
}
export interface WorkflowListItem extends Workflow {
  requestType: RequestType
}
export interface DraftCondition {
  clientId: string
  field_id: number | null
  operator: WorkflowConditionOperator | ''
  value: WorkflowConditionValue | null
}
export interface DraftWorkflowStep {
  clientId: string
  id?: number
  name: string
  approver_type: WorkflowApproverType
  approver_role: WorkflowApproverRole | null
  approver_user_id: number | null
  condition_logic: WorkflowConditionLogic
  conditions: DraftCondition[]
}
export interface WorkflowFormModel {
  request_type_id: number | null
  name: string
  description: string
  steps: DraftWorkflowStep[]
}
export interface WorkflowInput {
  name: string
  description: string | null
}
export interface WorkflowStepInput {
  name: string
  approver_type: WorkflowApproverType
  approver_role: WorkflowApproverRole | null
  approver_user_id: number | null
  condition_logic: WorkflowConditionLogic
  conditions: Array<{
    field_id: number
    operator: WorkflowConditionOperator
    value: WorkflowConditionValue
  }>
}
export interface ConditionField {
  id: number
  label: string
  key: string
  type: RequestFieldType
  config: RequestTypeField['config']
}

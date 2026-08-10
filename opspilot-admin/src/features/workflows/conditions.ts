import type { RequestTypeField, SelectOption } from '@/features/request-types/types/requestType'
import type {
  DraftCondition,
  WorkflowCondition,
  WorkflowConditionOperator,
  WorkflowConditionValue,
} from './types/workflow'

const equality: WorkflowConditionOperator[] = ['equals', 'not_equals']
const comparison: WorkflowConditionOperator[] = [
  ...equality,
  'greater_than',
  'greater_than_or_equal',
  'less_than',
  'less_than_or_equal',
]
const sets: WorkflowConditionOperator[] = [...equality, 'in', 'not_in']
export const operatorLabels: Record<WorkflowConditionOperator, string> = {
  equals: 'is equal to',
  not_equals: 'is not equal to',
  greater_than: 'is greater than',
  greater_than_or_equal: 'is at least',
  less_than: 'is less than',
  less_than_or_equal: 'is at most',
  in: 'is one of',
  not_in: 'is not one of',
  contains: 'contains',
  not_contains: 'does not contain',
}
export function operatorsForField(
  field?: Pick<RequestTypeField, 'type'>,
): WorkflowConditionOperator[] {
  if (!field) return []
  if (['number', 'decimal', 'date', 'datetime'].includes(field.type)) return comparison
  if (['text', 'textarea', 'email', 'url', 'select'].includes(field.type)) return sets
  if (field.type === 'multiselect') return ['contains', 'not_contains']
  if (field.type === 'boolean') return equality
  return []
}
export function defaultConditionValue(
  field: RequestTypeField,
  operator: WorkflowConditionOperator,
): WorkflowConditionValue {
  if (operator === 'in' || operator === 'not_in') return []
  if (field.type === 'number' || field.type === 'decimal') return 0
  if (field.type === 'boolean') return true
  const options = (field.config as { options?: SelectOption[] } | null)?.options
  return options?.[0]?.value ?? ''
}

export function normalizeNumericConditionInput(value: string | number): number | null {
  if (typeof value === 'string' && value.trim() === '') return null
  const numeric = typeof value === 'number' ? value : Number(value)
  return Number.isFinite(numeric) ? numeric : null
}

export function addStringSetValue(values: string[]) {
  return [...values, '']
}

export function updateStringSetValue(values: string[], index: number, value: string) {
  return values.map((item, itemIndex) => (itemIndex === index ? value : item))
}

export function removeStringSetValue(values: string[], index: number) {
  return values.filter((_, itemIndex) => itemIndex !== index)
}

export function normalizeStringSet(values: string[]) {
  return values.map((value) => value.trim())
}

export function validateStringSet(values: string[]) {
  const normalized = normalizeStringSet(values)
  if (!normalized.length) return 'Add at least one value.'
  if (normalized.some((value) => !value)) return 'Values cannot be empty.'
  if (new Set(normalized).size !== normalized.length) return 'Values must be unique.'
  return ''
}

export function datetimeEditorValue(value: unknown) {
  return typeof value === 'string'
    ? (value.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/)?.[1] ?? '')
    : ''
}

function localTimezoneOffset() {
  const minutes = -new Date().getTimezoneOffset()
  const sign = minutes >= 0 ? '+' : '-'
  const absolute = Math.abs(minutes)
  return `${sign}${String(Math.floor(absolute / 60)).padStart(2, '0')}:${String(absolute % 60).padStart(2, '0')}`
}

export function normalizeDatetimeEditorInput(editorValue: string, currentValue?: unknown) {
  if (!editorValue) return null
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(editorValue)) return null
  const offset =
    (typeof currentValue === 'string' ? currentValue.match(/([+-]\d{2}:\d{2})$/)?.[1] : null) ??
    localTimezoneOffset()
  return `${editorValue}:00${offset}`
}

export function isBackendDatetime(value: unknown): value is string {
  if (typeof value !== 'string') return false
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/.test(value)) return false
  return !Number.isNaN(Date.parse(value))
}
export function normalizeCondition(
  condition: DraftCondition,
  field: RequestTypeField,
): DraftCondition {
  const operators = operatorsForField(field)
  const operator = operators.includes(condition.operator as WorkflowConditionOperator)
    ? (condition.operator as WorkflowConditionOperator)
    : operators[0]!
  return {
    ...condition,
    field_id: field.id,
    operator,
    value: defaultConditionValue(field, operator),
  }
}
export function formatCondition(condition: WorkflowCondition, fields: RequestTypeField[] = []) {
  const field = fields.find((item) => item.id === condition.field.id)
  const label = field?.label ?? condition.field.label ?? condition.field.key
  const operator = operatorLabels[condition.operator] ?? condition.operator
  const options = (field?.config as { options?: SelectOption[] } | null)?.options ?? []
  const values = Array.isArray(condition.value) ? condition.value : [condition.value]
  const formatted = values
    .map((value) => {
      const option = options.find((item) => item.value === value)
      if (option) return option.label
      if (typeof value === 'boolean') return value ? 'Yes' : 'No'
      if (typeof value === 'number') return new Intl.NumberFormat('en').format(value)
      return String(value)
    })
    .join(', ')
  return `${label} ${operator} ${formatted}`
}

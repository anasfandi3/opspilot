import type { RequestTypeField, SelectOption } from '@/features/request-types/types/requestType'
import { isBackendDatetime, normalizeDatetimeEditorInput } from '@/features/workflows/conditions'
import type { RequestPayload, RequestValue } from './types/request'

export function initializeValues(fields: RequestTypeField[], payload: RequestPayload = {}) {
  return Object.fromEntries(
    fields.map((field) => [
      field.key,
      payload[field.key] ??
        (field.type === 'boolean' ? false : field.type === 'multiselect' ? [] : null),
    ]),
  ) as RequestPayload
}
export function serializeValues(fields: RequestTypeField[], values: RequestPayload) {
  const payload: RequestPayload = {}
  for (const field of fields) {
    let value = values[field.key]
    if (value === null || value === '' || (Array.isArray(value) && !value.length)) continue
    if (field.type === 'number' || field.type === 'decimal') value = Number(value)
    payload[field.key] = value ?? null
  }
  return payload
}
const options = (field: RequestTypeField) =>
  (field.config as { options?: SelectOption[] } | null)?.options ?? []
export function validateValues(
  fields: RequestTypeField[],
  values: RequestPayload,
  submitting: boolean,
) {
  const errors: Record<string, string[]> = {}
  const add = (key: string, message: string) => (errors[key] = [message])
  for (const field of fields) {
    const value = values[field.key]
    const empty = value === null || value === '' || (Array.isArray(value) && !value.length)
    if (submitting && field.is_required && empty) {
      add(field.key, 'This field is required.')
      continue
    }
    if (empty) continue
    const config = field.config as Record<string, unknown> | null
    if (
      (field.type === 'number' || field.type === 'decimal') &&
      (typeof value !== 'number' ||
        !Number.isFinite(value) ||
        (field.type === 'number' && !Number.isInteger(value)))
    )
      add(field.key, field.type === 'number' ? 'Enter a whole number.' : 'Enter a number.')
    else if (typeof value === 'number' && typeof config?.min === 'number' && value < config.min)
      add(field.key, `Enter ${config.min} or more.`)
    else if (typeof value === 'number' && typeof config?.max === 'number' && value > config.max)
      add(field.key, `Enter ${config.max} or less.`)
    else if (
      typeof value === 'string' &&
      typeof config?.min_length === 'number' &&
      value.length < config.min_length
    )
      add(field.key, `Enter at least ${config.min_length} characters.`)
    else if (
      typeof value === 'string' &&
      typeof config?.max_length === 'number' &&
      value.length > config.max_length
    )
      add(field.key, `Enter no more than ${config.max_length} characters.`)
    else if (field.type === 'email' && !/^\S+@\S+\.\S+$/.test(String(value)))
      add(field.key, 'Enter a valid email address.')
    else if (
      field.type === 'url' &&
      (() => {
        try {
          new URL(String(value))
          return false
        } catch {
          return true
        }
      })()
    )
      add(field.key, 'Enter a valid URL.')
    else if (field.type === 'datetime' && !isBackendDatetime(value))
      add(field.key, 'Enter a valid date and time.')
    else if (field.type === 'select' && !options(field).some((item) => item.value === value))
      add(field.key, 'Choose a configured option.')
    else if (
      field.type === 'multiselect' &&
      (!Array.isArray(value) ||
        new Set(value).size !== value.length ||
        value.some((item) => !options(field).some((option) => option.value === item)))
    )
      add(field.key, 'Choose valid, unique options.')
  }
  return errors
}
export function mapPayloadErrors(fieldErrors: Record<string, string[]>) {
  return Object.fromEntries(
    Object.entries(fieldErrors)
      .filter(([key]) => key.startsWith('payload.'))
      .map(([key, value]) => [key.slice(8), value]),
  )
}
export function updateDatetime(value: string, current: RequestValue) {
  return normalizeDatetimeEditorInput(value, current)
}
export function formatValue(field: RequestTypeField | undefined, value: unknown) {
  if (value === null || value === undefined || value === '') return '—'
  if (!field) return Array.isArray(value) ? value.join(', ') : String(value)
  if (field.type === 'boolean') return value ? 'Yes' : 'No'
  if (field.type === 'select' || field.type === 'multiselect') {
    const list = Array.isArray(value) ? value : [value]
    return list
      .map((entry) => options(field).find((item) => item.value === entry)?.label ?? String(entry))
      .join(', ')
  }
  if (field.type === 'number' || field.type === 'decimal')
    return new Intl.NumberFormat('en').format(Number(value))
  if (field.type === 'date') return String(value)
  if (field.type === 'datetime')
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(
      new Date(String(value)),
    )
  return String(value)
}

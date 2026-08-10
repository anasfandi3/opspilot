import type {
  DraftField,
  FieldConfig,
  RequestFieldType,
  RequestTypeField,
  RequestTypeFieldInput,
  RequestTypeFormModel,
  SelectOption,
} from './types/requestType'

export const fieldTypeLabels: Record<RequestFieldType, string> = {
  text: 'Text',
  textarea: 'Long text',
  number: 'Number',
  decimal: 'Decimal',
  boolean: 'Yes / No',
  date: 'Date',
  datetime: 'Date and time',
  select: 'Select',
  multiselect: 'Multi-select',
  email: 'Email',
  url: 'URL',
}

let clientFieldId = 0
export function createDraftField(type: RequestFieldType = 'text'): DraftField {
  clientFieldId += 1
  return {
    clientId: `field-${clientFieldId}`,
    key: '',
    label: '',
    type,
    description: '',
    is_required: false,
    config: defaultConfig(type),
  }
}

export function defaultConfig(type: RequestFieldType): FieldConfig {
  if (type === 'select' || type === 'multiselect') return { options: [{ value: '', label: '' }] }
  return null
}

export function normalizeFieldType(field: DraftField, type: RequestFieldType): DraftField {
  return { ...field, type, config: defaultConfig(type) }
}

export function hydrateField(field: RequestTypeField): DraftField {
  return {
    clientId: `persisted-${field.id}`,
    id: field.id,
    key: field.key,
    label: field.label,
    type: field.type,
    description: field.description ?? '',
    is_required: field.is_required,
    config: field.config ? (JSON.parse(JSON.stringify(field.config)) as FieldConfig) : null,
  }
}

export function hydrateRequestType(type?: {
  name: string
  description: string | null
  is_active: boolean
  fields: RequestTypeField[]
}): RequestTypeFormModel {
  return type
    ? {
        name: type.name,
        description: type.description ?? '',
        is_active: type.is_active,
        fields: [...type.fields].sort((a, b) => a.position - b.position).map(hydrateField),
      }
    : { name: '', description: '', is_active: true, fields: [] }
}

function compactConfig(field: DraftField): FieldConfig {
  const config = field.config
  if (field.type === 'select' || field.type === 'multiselect') {
    const options = (config as { options?: SelectOption[] } | null)?.options ?? []
    return {
      options: options.map((option) => ({
        value: option.value.trim(),
        label: option.label.trim(),
      })),
    }
  }
  if (field.type === 'text' || field.type === 'textarea') {
    const value = (config ?? {}) as { min_length?: number; max_length?: number }
    return compactNumbers(value, ['min_length', 'max_length'])
  }
  if (field.type === 'email' || field.type === 'url') {
    const value = (config ?? {}) as { max_length?: number }
    return compactNumbers(value, ['max_length'])
  }
  if (field.type === 'number' || field.type === 'decimal') {
    const value = (config ?? {}) as { min?: number; max?: number }
    return compactNumbers(value, ['min', 'max'])
  }
  return null
}

function compactNumbers(value: Record<string, number | undefined>, keys: string[]): FieldConfig {
  const result: Record<string, number> = {}
  for (const key of keys) if (value[key] !== undefined) result[key] = value[key]
  return Object.keys(result).length ? result : null
}

export function serializeField(field: DraftField): RequestTypeFieldInput {
  return {
    key: field.key.trim(),
    label: field.label.trim(),
    type: field.type,
    description: field.description.trim() || null,
    is_required: field.is_required,
    config: compactConfig(field),
  }
}

export function moveField(fields: DraftField[], from: number, to: number) {
  if (from < 0 || from >= fields.length || to < 0 || to >= fields.length || from === to) return
  const [field] = fields.splice(from, 1)
  if (field) fields.splice(to, 0, field)
}

export function validateOptions(options: SelectOption[]) {
  if (!options.length) return 'Add at least one option.'
  if (options.some((option) => !option.value.trim() || !option.label.trim()))
    return 'Option values and labels are required.'
  const values = options.map((option) => option.value.trim())
  return new Set(values).size === values.length ? '' : 'Option values must be unique.'
}

export function addOption(options: SelectOption[]) {
  return [...options, { value: '', label: '' }]
}
export function updateOption(
  options: SelectOption[],
  index: number,
  key: keyof SelectOption,
  value: string,
) {
  return options.map((option, optionIndex) =>
    optionIndex === index ? { ...option, [key]: value } : option,
  )
}
export function removeOption(options: SelectOption[], index: number) {
  return options.filter((_, optionIndex) => optionIndex !== index)
}

export function fieldIdsForOrder(fields: DraftField[]) {
  return fields.flatMap((field) => (field.id ? [field.id] : []))
}

export function fieldError(errors: Record<string, string[]>, index: number, path: string) {
  return errors[`fields.${index}.${path}`]?.[0] ?? ''
}

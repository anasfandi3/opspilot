export const requestFieldTypes = [
  'text',
  'textarea',
  'number',
  'decimal',
  'boolean',
  'date',
  'datetime',
  'select',
  'multiselect',
  'email',
  'url',
] as const

export type RequestFieldType = (typeof requestFieldTypes)[number]
export interface SelectOption {
  value: string
  label: string
}
export type FieldConfig =
  | { min_length?: number; max_length?: number }
  | { max_length?: number }
  | { min?: number; max?: number }
  | { options: SelectOption[] }
  | null
export interface RequestTypeField {
  id: number
  key: string
  label: string
  type: RequestFieldType
  description: string | null
  is_required: boolean
  position: number
  config: FieldConfig
}
export interface RequestType {
  id: number
  name: string
  slug: string
  description: string | null
  is_active: boolean
  creator: { id: number; name: string }
  fields: RequestTypeField[]
  created_at: string
  updated_at: string
}
export interface DraftField {
  clientId: string
  id?: number
  key: string
  label: string
  type: RequestFieldType
  description: string
  is_required: boolean
  config: FieldConfig
}
export interface RequestTypeFormModel {
  name: string
  description: string
  is_active: boolean
  fields: DraftField[]
}
export interface RequestTypeInput {
  name: string
  description: string | null
  is_active: boolean
}
export interface RequestTypeFieldInput {
  key: string
  label: string
  type: RequestFieldType
  description: string | null
  is_required: boolean
  config: FieldConfig
}

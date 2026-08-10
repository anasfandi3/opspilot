import type { FieldConfig, RequestTypeField } from '@/features/request-types/types/requestType'

type LengthConfig = { min_length?: number; max_length?: number }
type RangeConfig = { min?: number; max?: number }

export function lengthConfig(config: FieldConfig): LengthConfig {
  return config && ('min_length' in config || 'max_length' in config) ? config : {}
}

export function rangeConfig(config: FieldConfig): RangeConfig {
  return config && ('min' in config || 'max' in config) ? config : {}
}

export function numericInputValue(value: string, type: RequestTypeField['type']) {
  if (value.trim() === '') return null
  const numeric = Number(value)
  if (!Number.isFinite(numeric)) return null
  if (type === 'number' && !Number.isInteger(numeric)) return null
  return numeric
}

import type { ReportFilters } from './types/reports'
export function normalizeReportFilters(query: Record<string, unknown>): ReportFilters {
  const from =
    typeof query.from === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(query.from)
      ? query.from
      : undefined
  const to =
    typeof query.to === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(query.to) ? query.to : undefined
  const raw = Number(query.request_type_id)
  return {
    ...(from ? { from } : {}),
    ...(to ? { to } : {}),
    ...(Number.isInteger(raw) && raw > 0 ? { requestTypeId: raw } : {}),
  }
}
export function validateReportRange(from: string, to: string) {
  if (!from && !to) return ''
  if (!from || !to) return 'Choose both From and To dates.'
  const start = new Date(`${from}T00:00:00Z`),
    end = new Date(`${to}T00:00:00Z`)
  if (Number.isNaN(start.valueOf()) || Number.isNaN(end.valueOf())) return 'Enter valid dates.'
  if (end < start) return 'To date must be on or after From date.'
  const days = Math.floor((end.valueOf() - start.valueOf()) / 86400000) + 1
  return days > 366 ? 'The selected date range may not exceed 366 days.' : ''
}
export function filtersAfterWorkspaceSwitch(filters: ReportFilters): ReportFilters {
  const { requestTypeId: _, ...dates } = filters
  return dates
}
export function reportQueryForDates(filters: ReportFilters, from: string, to: string) {
  return {
    ...(from && to ? { from, to } : {}),
    ...(filters.requestTypeId ? { request_type_id: String(filters.requestTypeId) } : {}),
  }
}
export function resetReportQuery() {
  return {}
}

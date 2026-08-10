import { describe, expect, it } from 'vitest'
import { reportKeys } from '@/features/reports/queries/reportKeys'
import {
  filtersAfterWorkspaceSwitch,
  normalizeReportFilters,
  reportQueryForDates,
  resetReportQuery,
  validateReportRange,
} from '@/features/reports/reportFilters'
import {
  formatDuration,
  nullMetric,
  periodLabel,
  trendBarHeight,
} from '@/features/reports/reportFormatting'
import { reportRoutes } from '@/features/reports/routes'

describe('dashboard and reports', () => {
  it('isolates every report query by workspace and normalized filters', () => {
    expect(reportKeys.dashboard(1)).not.toEqual(reportKeys.dashboard(2))
    expect(reportKeys.requests(1, {})).not.toEqual(
      reportKeys.requests(1, { from: '2026-01-01', to: '2026-01-30' }),
    )
    expect(reportKeys.approvals(1, { requestTypeId: 4 })).not.toEqual(
      reportKeys.approvals(2, { requestTypeId: 4 }),
    )
  })
  it('normalizes empty, complete-date, and request-type filters', () => {
    expect(normalizeReportFilters({})).toEqual({})
    expect(normalizeReportFilters({ from: '2026-01-01', to: '2026-01-30' })).toEqual({
      from: '2026-01-01',
      to: '2026-01-30',
    })
    expect(normalizeReportFilters({ request_type_id: '7' })).toEqual({ requestTypeId: 7 })
  })
  it('validates paired, ordered, inclusive 366-day ranges', () => {
    expect(validateReportRange('2026-01-01', '')).toContain('both')
    expect(validateReportRange('2026-02-01', '2026-01-01')).toContain('on or after')
    expect(validateReportRange('2024-01-01', '2024-12-31')).toBe('')
    expect(validateReportRange('2024-01-01', '2025-01-01')).toContain('366')
  })
  it('formats null, minute, hour, and multi-day duration metrics', () => {
    expect(formatDuration(null)).toBe('—')
    expect(formatDuration(0.5)).toBe('30m')
    expect(formatDuration(2)).toBe('2h')
    expect(formatDuration(26.5)).toBe('1d 2h 30m')
    expect(nullMetric(null, 'No decisions yet')).toBe('No decisions yet')
    expect(periodLabel({ from: '2026-01-01', to: '2026-01-30', timezone: 'UTC' })).toContain('UTC')
  })
  it('clears only tenant-specific request type on workspace switch', () => {
    expect(
      filtersAfterWorkspaceSwitch({ from: '2026-01-01', to: '2026-01-30', requestTypeId: 7 }),
    ).toEqual({ from: '2026-01-01', to: '2026-01-30' })
  })
  it('renders zero trend values truthfully and preserves external request type on date apply', () => {
    expect(trendBarHeight(0, 10)).toBe(0)
    expect(trendBarHeight(1, 100)).toBe(2)
    const filters = normalizeReportFilters({ request_type_id: '7' })
    expect(reportQueryForDates(filters, '2026-08-01', '2026-08-10')).toEqual({
      from: '2026-08-01',
      to: '2026-08-10',
      request_type_id: '7',
    })
    expect(resetReportQuery()).toEqual({})
  })
  it('protects every report route with the exact report permission', () => {
    expect(reportRoutes.map((route) => route.meta)).toEqual([
      expect.objectContaining({
        requiresAuth: true,
        requiresWorkspace: true,
        permission: 'reports.view',
      }),
      expect.objectContaining({
        requiresAuth: true,
        requiresWorkspace: true,
        permission: 'reports.view',
      }),
      expect.objectContaining({
        requiresAuth: true,
        requiresWorkspace: true,
        permission: 'reports.view',
      }),
    ])
  })
})

export function formatDuration(hours: number | null) {
  if (hours === null) return '—'
  const minutes = Math.round(hours * 60)
  const days = Math.floor(minutes / 1440),
    remaining = minutes % 1440
  const hrs = Math.floor(remaining / 60),
    mins = remaining % 60
  return (
    [days ? `${days}d` : '', hrs ? `${hrs}h` : '', mins ? `${mins}m` : '']
      .filter(Boolean)
      .join(' ') || '0 min'
  )
}
export function periodLabel(period: { from: string; to: string; timezone: string }) {
  return `${period.from} to ${period.to} (${period.timezone})`
}
export function nullMetric(value: number | null, fallback = 'No data') {
  return value === null ? fallback : value
}
export function trendBarHeight(value: number, maximum: number) {
  if (value === 0) return 0
  return Math.max(2, (value / Math.max(1, maximum)) * 100)
}

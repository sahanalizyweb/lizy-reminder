// API dates are plain YYYY-MM-DD strings. They are formatted by splitting the
// string, never through `new Date(string)`, so timezones cannot shift the day.

/** 2026-09-23 -> 23/09/2026 */
export function formatDate(iso) {
  if (!iso) return '—'
  const [year, month, day] = iso.slice(0, 10).split('-')
  return `${day}/${month}/${year}`
}

/** 2026-09-23 -> Wed, 23 Sep 2026 */
export function formatLongDate(iso) {
  if (!iso) return ''
  const [year, month, day] = iso.slice(0, 10).split('-').map(Number)
  return new Date(year, month - 1, day).toLocaleDateString('en-IN', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

export function formatDays(count) {
  return `${count} ${count === 1 ? 'day' : 'days'}`
}

/** Full timestamp for Change History entries, e.g. "9/21/2026, 10:05:20 AM". */
export function formatDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-US')
}

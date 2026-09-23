export const EMPTY_FILTERS = {
  search: '',
  reminder_type: '',
  assigned_to: '',
  status: '',
  range: '',
  date_from: '',
  date_to: '',
}

/** Filters that pick a date range (as opposed to search / type / staff / status). */
export const DATE_FILTERS = { range: '', date_from: '', date_to: '' }

/** Query parameters for the API: empty values are dropped, "custom" is just date_from/date_to. */
export function toQuery(filters) {
  const query = {}
  for (const [key, value] of Object.entries(filters)) {
    if (value === '' || value == null) continue
    if (key === 'range' && value === 'custom') continue
    query[key] = value
  }
  return query
}

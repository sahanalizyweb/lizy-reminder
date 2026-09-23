import { useState } from 'react'
import { DATE_FILTERS } from '../utils/filters'
import { RANGE_OPTIONS, REMINDER_TYPES, STATUS_OPTIONS, reminderTypeLabel } from '../utils/constants'

/**
 * Filters for a reminder table. The fields edit a draft; "Apply Filters" (or
 * Enter) hands it to the page, "Clear" resets everything. The quick date
 * ranges (shown on the Upcoming view) apply as soon as they are clicked.
 */
export default function FilterPanel({ applied, staff, showRange = false, showAssignedFilter = true, onApply, onClear }) {
  const [draft, setDraft] = useState(applied)
  const [seen, setSeen] = useState(applied)

  // Keep the fields in step when the page changes the filters (view switch, Clear).
  if (seen !== applied) {
    setSeen(applied)
    setDraft(applied)
  }

  const set = (name) => (event) => setDraft((current) => ({ ...current, [name]: event.target.value }))

  // Typing a date means a custom range.
  const setDate = (name) => (event) =>
    setDraft((current) => ({ ...current, [name]: event.target.value, range: showRange ? 'custom' : '' }))

  const chooseRange = (range) => onApply({ ...draft, ...DATE_FILTERS, range })

  const submit = (event) => {
    event.preventDefault()
    onApply(draft)
  }

  return (
    <form className="filters" onSubmit={submit}>
      {showRange && (
        <div className="filters__ranges" role="group" aria-label="Date range">
          {RANGE_OPTIONS.map((option) => (
            <button
              key={option.value}
              type="button"
              className={`chip ${draft.range === option.value ? 'is-active' : ''}`}
              onClick={() => (option.value === 'custom' ? setDraft((d) => ({ ...d, range: 'custom' })) : chooseRange(option.value))}
            >
              {option.label}
            </button>
          ))}
        </div>
      )}

      <div className="filters__grid">
        <label className="field field--search">
          <span>Search</span>
          <input
            type="search"
            placeholder="Customer, phone, product, service, booking, property or notes"
            value={draft.search}
            onChange={set('search')}
          />
        </label>

        <label className="field">
          <span>Reminder Type</span>
          <select value={draft.reminder_type} onChange={set('reminder_type')}>
            <option value="">All Types</option>
            {REMINDER_TYPES.map((type) => (
              <option key={type} value={type}>
                {reminderTypeLabel(type)}
              </option>
            ))}
          </select>
        </label>

        {showAssignedFilter && (
          <label className="field">
            <span>Assigned Person</span>
            <select value={draft.assigned_to} onChange={set('assigned_to')}>
              <option value="">All Staff</option>
              {staff.map((person) => (
                <option key={person.id} value={person.id}>
                  {person.name}
                </option>
              ))}
              <option value="unassigned">Unassigned</option>
            </select>
          </label>
        )}

        <label className="field">
          <span>Status</span>
          <select value={draft.status} onChange={set('status')}>
            <option value="">All Statuses</option>
            {STATUS_OPTIONS.map((status) => (
              <option key={status}>{status}</option>
            ))}
          </select>
        </label>

        <label className="field">
          <span>Date From</span>
          <input type="date" value={draft.date_from} max={draft.date_to || undefined} onChange={setDate('date_from')} />
        </label>

        <label className="field">
          <span>Date To</span>
          <input type="date" value={draft.date_to} min={draft.date_from || undefined} onChange={setDate('date_to')} />
        </label>

        <div className="filters__buttons">
          <button type="submit" className="btn btn--primary">
            Apply Filters
          </button>
          <button type="button" className="btn btn--ghost" onClick={onClear}>
            Clear
          </button>
        </div>
      </div>
    </form>
  )
}

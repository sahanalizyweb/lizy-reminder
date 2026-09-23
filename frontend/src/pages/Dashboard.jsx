import { useCallback, useState } from 'react'
import { useLocation } from 'react-router-dom'
import FilterPanel from '../components/FilterPanel'
import ReminderResults from '../components/ReminderResults'
import SummaryCards from '../components/SummaryCards'
import { useAuth } from '../hooks/useAuth'
import { useReminders, useSummary } from '../hooks/useReminders'
import { useStaff } from '../hooks/useStaff'
import { formatLongDate } from '../utils/date'
import { ROLE_USER, VIEWS } from '../utils/constants'
import { DATE_FILTERS, EMPTY_FILTERS, toQuery } from '../utils/filters'

const PER_PAGE = 15

const EMPTY_TEXT = {
  today: 'Nothing is due today.',
  upcoming: 'No upcoming reminders match these filters.',
  overdue: 'No overdue or expired reminders. Nice work!',
  completed: 'No completed reminders match these filters.',
}

/**
 * Today's Work / Upcoming / Overdue / Completed. The counts are the tabs: each
 * one is a real database filter (the `view` parameter) on the same table.
 */
export default function Dashboard() {
  const location = useLocation()
  const { user } = useAuth()
  const { staff } = useStaff()
  const [view, setView] = useState(location.state?.view ?? 'today')
  const [filters, setFilters] = useState(EMPTY_FILTERS)
  const [page, setPage] = useState(1)

  const list = useReminders({ view, ...toQuery(filters), page, per_page: PER_PAGE })
  // Counts follow the search / type / staff filters, but not the view or date filters.
  const { summary, reload: reloadSummary } = useSummary({
    search: filters.search,
    reminder_type: filters.reminder_type,
    assigned_to: filters.assigned_to,
  })

  const selectView = (next) => {
    setView(next)
    setPage(1)
    setFilters((current) => ({ ...current, ...DATE_FILTERS, status: '' }))
  }

  const applyFilters = (next) => {
    setFilters(next)
    setPage(1)
  }

  const clearFilters = () => applyFilters(EMPTY_FILTERS)

  const changed = () => {
    list.reload()
    reloadSummary()
  }

  const { label, description, columns } = VIEWS[view]
  const setPageStable = useCallback((next) => setPage(next), [])

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <h2>{label}</h2>
          <p className="muted">
            {description}
            {summary?.date && <> · Today is {formatLongDate(summary.date)}</>}
          </p>
        </div>
      </div>

      <SummaryCards summary={summary} activeView={view} onSelect={selectView} />

      <FilterPanel
        applied={filters}
        staff={staff}
        showRange={view === 'upcoming'}
        showAssignedFilter={user.role !== ROLE_USER}
        onApply={applyFilters}
        onClear={clearFilters}
      />

      <ReminderResults
        rows={list.rows}
        meta={list.meta}
        loading={list.loading}
        error={list.error}
        columns={columns}
        typeFilter={filters.reminder_type}
        emptyText={EMPTY_TEXT[view]}
        onPage={setPageStable}
        onChanged={changed}
      />
    </div>
  )
}

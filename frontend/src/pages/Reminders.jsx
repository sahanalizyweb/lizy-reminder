import { useCallback, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import ExportExcelButton from '../components/ExportExcelButton'
import FilterPanel from '../components/FilterPanel'
import ReminderResults from '../components/ReminderResults'
import { useAuth } from '../hooks/useAuth'
import { useReminders } from '../hooks/useReminders'
import { useStaff } from '../hooks/useStaff'
import { ROLE_USER } from '../utils/constants'
import { EMPTY_FILTERS, toQuery } from '../utils/filters'

const PER_PAGE = 15

/** Every reminder, newest first, with the full set of filters. */
export default function Reminders() {
  const { user } = useAuth()
  const { staff } = useStaff()
  const location = useLocation()
  const [filters, setFilters] = useState(EMPTY_FILTERS)
  const [page, setPage] = useState(1)
  // Arriving from a notification-bell alert opens that reminder's View modal directly.
  const [openReminderId] = useState(location.state?.openReminderId ?? null)

  const list = useReminders({ ...toQuery(filters), page, per_page: PER_PAGE })

  const applyFilters = (next) => {
    setFilters(next)
    setPage(1)
  }
  const setPageStable = useCallback((next) => setPage(next), [])

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <h2>All Reminders</h2>
          <p className="muted">{list.meta ? `${list.meta.total} reminder${list.meta.total === 1 ? '' : 's'}` : 'Loading…'}</p>
        </div>
        <div className="page-head__actions">
          <ExportExcelButton filters={filters} />
          <Link to="/reminders/new" className="btn btn--primary">
            Add Reminder
          </Link>
        </div>
      </div>

      <FilterPanel
        applied={filters}
        staff={staff}
        showAssignedFilter={user.role !== ROLE_USER}
        onApply={applyFilters}
        onClear={() => applyFilters(EMPTY_FILTERS)}
      />

      <ReminderResults
        rows={list.rows}
        meta={list.meta}
        loading={list.loading}
        error={list.error}
        columns="standard"
        typeFilter={filters.reminder_type}
        initialViewId={openReminderId}
        onPage={setPageStable}
        onChanged={list.reload}
      />
    </div>
  )
}

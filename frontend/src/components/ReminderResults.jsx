import { useEffect, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useToast } from '../hooks/useToast'
import { reminderService } from '../services/reminderService'
import ConfirmDialog from './ConfirmDialog'
import Pagination from './Pagination'
import ReminderModal from './ReminderModal'
import ReminderTable from './ReminderTable'

/**
 * A page of reminders with everything that happens to a row: view, edit,
 * complete and delete (with confirmation). `onChanged` tells the page to reload
 * its list and counts after the database has changed. `initialViewId`, when
 * given, opens that reminder's View modal straight away — e.g. arriving from
 * a notification-bell alert — fetching it directly rather than requiring it
 * to already be on the current (filtered/paginated) page.
 */
export default function ReminderResults({ rows, meta, loading, error, columns, typeFilter, emptyText, initialViewId, onPage, onChanged }) {
  const navigate = useNavigate()
  const location = useLocation()
  const toast = useToast()
  const [viewing, setViewing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  // Deleting the last row of the last page leaves an empty page: step back.
  useEffect(() => {
    if (meta && meta.current_page > meta.last_page) onPage(meta.last_page)
  }, [meta, onPage])

  useEffect(() => {
    if (!initialViewId) return
    reminderService
      .get(initialViewId)
      .then(setViewing)
      .catch((err) => toast.error(err.status === 403 ? 'You do not have access to that reminder.' : err.message))
    // Only ever react to the id itself, not to toast (a new function identity every render).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialViewId])

  const actions = {
    onView: setViewing,
    onEdit: (reminder) => navigate(`/reminders/${reminder.id}/edit`, { state: { from: location.pathname } }),
    onComplete: async (reminder) => {
      try {
        await reminderService.complete(reminder.id)
        toast.success(`Reminder #${reminder.id} marked as completed.`)
        setViewing(null)
        onChanged()
      } catch (err) {
        toast.error(err.message)
      }
    },
    onDelete: (reminder) => {
      setViewing(null)
      setDeleting(reminder)
    },
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await reminderService.remove(deleting.id)
      toast.success(`Reminder #${deleting.id} deleted.`)
      setDeleting(null)
      onChanged()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      {error && (
        <div className="alert alert--error" role="alert">
          {error.message}{' '}
          <button type="button" className="link-btn" onClick={onChanged}>
            Try again
          </button>
        </div>
      )}

      <ReminderTable rows={rows} columns={columns} typeFilter={typeFilter} loading={loading} emptyText={emptyText} actions={actions} />
      <Pagination meta={meta} onPage={onPage} />

      {viewing && <ReminderModal reminder={viewing} actions={actions} onClose={() => setViewing(null)} />}
      {deleting && (
        <ConfirmDialog
          title="Delete reminder?"
          message={`Delete reminder #${deleting.id} (${deleting.product_name || deleting.website_link || deleting.booking_name || deleting.property_name || deleting.reminder_type} for ${deleting.customer_name})? This cannot be undone.`}
          confirmLabel="Delete"
          danger
          busy={busy}
          onConfirm={confirmDelete}
          onCancel={() => setDeleting(null)}
        />
      )}
    </>
  )
}

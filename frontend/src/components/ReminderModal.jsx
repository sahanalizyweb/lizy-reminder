import { useApi } from '../hooks/useApi'
import { reminderService } from '../services/reminderService'
import { formatDate, formatDateTime, formatDays } from '../utils/date'
import { TYPE_IT_SERVICE, TYPE_PRODUCT, TYPE_REAL_ESTATE, TYPE_TRAVEL, reminderTypeLabel } from '../utils/constants'
import Modal from './Modal'
import PhoneActions from './PhoneActions'
import StatusBadge from './StatusBadge'

function Detail({ label, children }) {
  return (
    <div className="detail">
      <dt>{label}</dt>
      <dd>{children}</dd>
    </div>
  )
}

// scheduled_date / reminder_date are stored as raw ISO dates so the app-wide dd/mm/yyyy
// formatting applies; every other field already carries a display-ready string.
const DATE_FIELDS = new Set(['scheduled_date', 'reminder_date'])

function changeValue(field, value) {
  return DATE_FIELDS.has(field) ? formatDate(value) : value
}

/** Change History: only edits made after creation ever appear here (see backend/README). */
function ChangeHistory({ reminderId }) {
  const { data: entries, loading } = useApi(reminderService.history, reminderId)

  if (loading) return <p className="muted small">Loading history…</p>
  if (!entries || entries.length === 0) return <p className="muted small">No changes yet.</p>

  return (
    <ul className="history-list">
      {entries.map((entry) => (
        <li key={entry.id}>
          <b>{entry.field_label}</b>: {changeValue(entry.field, entry.previous_value)} → {changeValue(entry.field, entry.new_value)} by{' '}
          {entry.changed_by_name} on {formatDateTime(entry.created_at)}
        </li>
      ))}
    </ul>
  )
}

/** Read-only view of one reminder, with the follow-up (call / WhatsApp) and row actions. */
export default function ReminderModal({ reminder, actions, onClose }) {
  const pastDue = reminder.status === 'Overdue' || reminder.status === 'Expired'
  const isProduct = reminder.reminder_type === TYPE_PRODUCT
  const isItService = reminder.reminder_type === TYPE_IT_SERVICE
  const isTravel = reminder.reminder_type === TYPE_TRAVEL
  const isRealEstate = reminder.reminder_type === TYPE_REAL_ESTATE
  const dateLabel = isTravel ? 'Travel Date' : isRealEstate ? 'Marketing Follow-up Date' : 'Scheduled / Due Date'

  return (
    <Modal
      title={`Reminder #${reminder.id}`}
      onClose={onClose}
      wide
      footer={
        <>
          <button type="button" className="btn btn--danger-outline" onClick={() => actions.onDelete(reminder)}>
            Delete
          </button>
          <span className="spacer" />
          <button type="button" className="btn btn--ghost" onClick={() => actions.onEdit(reminder)}>
            Edit
          </button>
          <button type="button" className="btn btn--success" disabled={reminder.is_completed} onClick={() => actions.onComplete(reminder)}>
            {reminder.is_completed ? 'Completed' : 'Mark Complete'}
          </button>
        </>
      }
    >
      <dl className="details">
        <Detail label="Customer / Company">{reminder.customer_name}</Detail>
        <Detail label="Status">
          <StatusBadge status={reminder.status} />
          {pastDue && <span className="overdue-days"> · {formatDays(reminder.days_overdue)} overdue</span>}
        </Detail>
        <Detail label="Phone (follow-up)">
          <PhoneActions reminder={reminder} />
        </Detail>
        <Detail label="Reminder Type">{reminderTypeLabel(reminder.reminder_type)}</Detail>

        {isProduct && (
          <>
            <Detail label="Product Name">{reminder.product_name}</Detail>
            <Detail label="Product Category">{reminder.product_category_name ?? '—'}</Detail>
            <Detail label="Quantity">{reminder.quantity}</Detail>
            <Detail label="Price">{reminder.price != null ? reminder.price : '—'}</Detail>
          </>
        )}

        {isItService && (
          <Detail label="Website Link">
            {reminder.website_link ? (
              <a href={reminder.website_link} target="_blank" rel="noopener noreferrer">
                {reminder.website_link}
              </a>
            ) : (
              '—'
            )}
          </Detail>
        )}

        {isTravel && <Detail label="Booking / Tour Name">{reminder.booking_name}</Detail>}

        {isRealEstate && (
          <>
            <Detail label="Property / Project Name">{reminder.property_name}</Detail>
            <Detail label="Location">{reminder.location}</Detail>
          </>
        )}

        {!isProduct && !isItService && !isTravel && !isRealEstate && reminder.product_name && (
          <Detail label="Product / Service">{reminder.product_name}</Detail>
        )}

        <Detail label="Assigned To">{reminder.assigned_to_name ?? 'Unassigned'}</Detail>
        <Detail label={dateLabel}>{formatDate(reminder.scheduled_date)}</Detail>
        <Detail label="Reminder Date">{formatDate(reminder.reminder_date)}</Detail>
        <Detail label="Notes">
          <span className="pre-wrap">{reminder.notes || '—'}</span>
        </Detail>
        <Detail label="Added">{formatDate(reminder.created_at)}</Detail>
        {reminder.completed_at && <Detail label="Completed On">{formatDate(reminder.completed_at)}</Detail>}
      </dl>

      <div className="history">
        <h3>Change History</h3>
        <ChangeHistory reminderId={reminder.id} />
      </div>
    </Modal>
  )
}

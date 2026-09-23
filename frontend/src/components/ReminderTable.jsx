import { formatDate, formatDays } from '../utils/date'
import { TYPE_IT_SERVICE, TYPE_PRODUCT, TYPE_REAL_ESTATE, TYPE_TRAVEL } from '../utils/constants'
import PhoneActions from './PhoneActions'
import StatusBadge from './StatusBadge'

const NO_CUSTOMER = 'Not Provided'

const DATE_LABELS = {
  [TYPE_TRAVEL]: 'Travel Date',
  [TYPE_REAL_ESTATE]: 'Follow-up Date',
}

/**
 * Columns for the given view ('standard' | 'overdue') and reminder-type
 * filter. Filtered to Product: ID/Customer/Phone/Type/Product/Category/Qty/
 * dates/Assigned/Status/Notes/Action. Filtered to IT Service: the Product/
 * Category/Qty columns are replaced by a single Website Link column.
 * Filtered to Travel: a single Booking / Tour Name column. Filtered to Real
 * Estate: Property / Project Name and Location columns. With no type filter
 * (every kind of reminder in the same table) the first of those columns
 * doubles up as whichever one applies to each row (see the `product_name`
 * cell), Category/Qty/Location read "—" where not relevant, and the date
 * column keeps its generic "Scheduled Date" / "Due Date" label since it
 * can't vary per row.
 */
function buildColumns(view, typeFilter) {
  const middle =
    typeFilter === TYPE_IT_SERVICE
      ? [['website', 'Website Link']]
      : typeFilter === TYPE_TRAVEL
        ? [['product_name', 'Booking / Tour Name']]
        : typeFilter === TYPE_REAL_ESTATE
          ? [
              ['product_name', 'Property / Project Name'],
              ['location', 'Location'],
            ]
          : [
              ['product_name', typeFilter === TYPE_PRODUCT ? 'Product' : 'Details'],
              ['category', 'Category'],
              ['qty', 'Qty'],
              ...(typeFilter ? [] : [['location', 'Location']]),
            ]

  const dateLabel = DATE_LABELS[typeFilter] ?? (view === 'overdue' ? 'Due Date' : 'Scheduled Date')
  const dates =
    view === 'overdue'
      ? [
          ['due', dateLabel],
          ['overdue', 'Days Overdue'],
        ]
      : [
          ['scheduled', dateLabel],
          ['reminder', 'Reminder Date'],
        ]

  return [
    ['id', 'ID'],
    ['customer', 'Customer'],
    ['phone', 'Phone'],
    ['type', 'Type'],
    ...middle,
    ...dates,
    ['assigned', 'Assigned To'],
    ['status', 'Status'],
    ['notes', 'Notes'],
    ['action', 'Action'],
  ]
}

function WebsiteLink({ reminder }) {
  if (!reminder.website_link) return <span className="muted">—</span>
  return (
    <a className="link-cell" href={reminder.website_link} target="_blank" rel="noopener noreferrer" title={reminder.website_link}>
      {reminder.website_link}
    </a>
  )
}

function Cell({ column, reminder, actions }) {
  switch (column) {
    case 'id':
      return <td className="col-id">{reminder.id}</td>
    case 'customer':
      return <td className={`col-customer ${reminder.customer_name === NO_CUSTOMER ? 'muted' : 'strong'}`}>{reminder.customer_name}</td>
    case 'phone':
      return (
        <td>
          <PhoneActions reminder={reminder} />
        </td>
      )
    case 'type':
      return <td>{reminder.reminder_type}</td>
    case 'product_name': {
      if (reminder.reminder_type === TYPE_IT_SERVICE) {
        return (
          <td className="col-website">
            <WebsiteLink reminder={reminder} />
          </td>
        )
      }
      const label = reminder.product_name || reminder.booking_name || reminder.property_name
      return <td>{label || <span className="muted">—</span>}</td>
    }
    case 'website':
      return (
        <td className="col-website">
          <WebsiteLink reminder={reminder} />
        </td>
      )
    case 'category':
      return <td>{reminder.reminder_type === TYPE_PRODUCT ? reminder.product_category_name || <span className="muted">—</span> : <span className="muted">—</span>}</td>
    case 'qty':
      return <td className="nowrap">{reminder.reminder_type === TYPE_PRODUCT ? reminder.quantity : <span className="muted">—</span>}</td>
    case 'location':
      return <td>{reminder.location || <span className="muted">—</span>}</td>
    case 'scheduled':
    case 'due':
      return <td className="nowrap">{formatDate(reminder.scheduled_date)}</td>
    case 'reminder':
      return <td className="nowrap">{formatDate(reminder.reminder_date)}</td>
    case 'overdue':
      return <td className="nowrap overdue-days">{formatDays(reminder.days_overdue)}</td>
    case 'assigned':
      return <td className={reminder.assigned_to_name ? '' : 'muted'}>{reminder.assigned_to_name ?? 'Unassigned'}</td>
    case 'status':
      return (
        <td>
          <StatusBadge status={reminder.status} />
        </td>
      )
    case 'notes':
      return (
        <td className="col-notes">
          <div className="notes" title={reminder.notes ?? ''}>
            {reminder.notes || <span className="muted">—</span>}
          </div>
        </td>
      )
    case 'action':
      return (
        <td className="col-action">
          <div className="row-actions">
            <button type="button" className="btn btn--sm btn--ghost" onClick={() => actions.onView(reminder)}>
              View
            </button>
            <button type="button" className="btn btn--sm btn--ghost" onClick={() => actions.onEdit(reminder)}>
              Edit
            </button>
            <button
              type="button"
              className="btn btn--sm btn--success"
              disabled={reminder.is_completed}
              onClick={() => actions.onComplete(reminder)}
            >
              Complete
            </button>
            <button type="button" className="btn btn--sm btn--danger-outline" onClick={() => actions.onDelete(reminder)}>
              Delete
            </button>
          </div>
        </td>
      )
    default:
      return null
  }
}

/**
 * The reminder table. It lives in a container that scrolls sideways (drag the
 * scrollbar, use the trackpad, or Shift + mouse wheel), so the page itself
 * never scrolls horizontally. The header stays put while scrolling down.
 */
export default function ReminderTable({ rows, columns = 'standard', typeFilter = '', loading, emptyText = 'No reminders found.', actions }) {
  const layout = buildColumns(columns, typeFilter)

  return (
    <div className={`table-scroll ${loading ? 'is-loading' : ''}`} tabIndex={0} aria-busy={loading}>
      <table className="reminder-table">
        <thead>
          <tr>
            {layout.map(([key, label]) => (
              <th key={key} scope="col" className={key === 'action' ? 'col-action' : undefined}>
                {label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((reminder) => (
            <tr key={reminder.id}>
              {layout.map(([key]) => (
                <Cell key={key} column={key} reminder={reminder} actions={actions} />
              ))}
            </tr>
          ))}
          {rows.length === 0 && (
            <tr className="empty-row">
              <td colSpan={layout.length}>{loading ? 'Loading reminders…' : emptyText}</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}

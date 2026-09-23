import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useNotifications } from '../hooks/useNotifications'
import { formatDate } from '../utils/date'
import Icon from './Icon'

function NotificationItem({ item, onSelect }) {
  return (
    <li>
      <button type="button" className="notif-item notif-item--today" onClick={() => onSelect(item)}>
        <span className="notif-item__type notif-item__type--today">Today</span>
        <span className="notif-item__body">
          <span className="strong">{item.customer_name}</span>
          <span className="muted small">{item.label}</span>
          <span className="muted small">Reminder: {formatDate(item.reminder_date)}</span>
        </span>
      </button>
    </li>
  )
}

/**
 * Bell icon with an unread-count badge for today's reminders only. Click
 * opens a small panel; opening a notification marks it read (removing it
 * from the count immediately) and takes you to that reminder's View page.
 */
export default function NotificationBell() {
  const navigate = useNavigate()
  const { count, items, loading, reload, markRead } = useNotifications()
  const [open, setOpen] = useState(false)
  const rootRef = useRef(null)

  // Close on outside click or Escape; refresh every time it's opened.
  useEffect(() => {
    if (!open) return

    reload()
    const onPointerDown = (event) => {
      if (!rootRef.current?.contains(event.target)) setOpen(false)
    }
    const onKeyDown = (event) => {
      if (event.key === 'Escape') setOpen(false)
    }
    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  const selectItem = (item) => {
    markRead(item.id)
    setOpen(false)
    navigate('/reminders', { state: { openReminderId: item.id } })
  }

  const displayCount = count > 99 ? '99+' : count

  return (
    <div className="notif" ref={rootRef}>
      <button
        type="button"
        className="icon-btn notif__trigger"
        onClick={() => setOpen((value) => !value)}
        aria-label={count > 0 ? `Notifications (${count} unread)` : 'Notifications'}
        aria-expanded={open}
      >
        <Icon name="bell" size={18} />
        {count > 0 && <span className="notif__badge">{displayCount}</span>}
      </button>

      {open && (
        <div className="notif__panel" role="menu">
          <div className="notif__header">
            <span>Today's Reminders</span>
            {count > 0 && <span className="muted small">{count} unread</span>}
          </div>

          {loading && items.length === 0 && <p className="notif__empty muted">Loading…</p>}
          {!loading && items.length === 0 && <p className="notif__empty muted">Nothing due today. Nice work!</p>}

          {items.length > 0 && (
            <ul className="notif__list">
              {items.map((item) => (
                <NotificationItem key={item.id} item={item} onSelect={selectItem} />
              ))}
            </ul>
          )}

          {count > items.length && (
            <div className="notif__footer muted small">
              Showing {items.length} of {count}
            </div>
          )}
        </div>
      )}
    </div>
  )
}

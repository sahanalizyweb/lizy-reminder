import { NavLink } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { ROLE_ADMIN } from '../utils/constants'
import Icon from './Icon'

const NAV = [
  { to: '/', label: 'Dashboard', icon: 'dashboard', end: true },
  { to: '/reminders', label: 'All Reminders', icon: 'list', end: true },
  { to: '/reminders/new', label: 'Add Reminder', icon: 'plus', end: true },
]

const ADMIN_NAV = { to: '/users', label: 'Manage Users', icon: 'users', end: false }

/** Fixed on desktop; slides in over the page (with a backdrop) on tablet and mobile. */
export default function Sidebar({ open, onClose }) {
  const { user } = useAuth()
  const items = user?.role === ROLE_ADMIN ? [...NAV, ADMIN_NAV] : NAV

  return (
    <>
      <div className={`sidebar-backdrop ${open ? 'is-open' : ''}`} onClick={onClose} aria-hidden="true" />
      <aside className={`sidebar ${open ? 'is-open' : ''}`} aria-label="Main navigation">
        <div className="sidebar__brand">
          <span className="sidebar__logo">
            <Icon name="bell" size={20} />
          </span>
          <span>
            LIZY <b>REMINDER</b>
          </span>
        </div>
        <nav className="sidebar__nav">
          {items.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end} className="sidebar__link" onClick={onClose}>
              <Icon name={item.icon} />
              <span>{item.label}</span>
            </NavLink>
          ))}
        </nav>
        <div className="sidebar__footer">Lizyweb internal</div>
      </aside>
    </>
  )
}

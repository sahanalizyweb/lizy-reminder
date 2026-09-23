import { useAuth } from '../hooks/useAuth'
import Icon from './Icon'
import NotificationBell from './NotificationBell'

export default function Header({ title, onMenu }) {
  const { user, logout } = useAuth()

  return (
    <header className="header">
      <button type="button" className="icon-btn header__menu" onClick={onMenu} aria-label="Open menu">
        <Icon name="menu" size={22} />
      </button>
      <h1 className="header__title">{title}</h1>
      <div className="header__user">
        <NotificationBell />
        <span className="header__name" title={user?.email}>
          {user?.name}
        </span>
        <button type="button" className="btn btn--ghost btn--sm" onClick={logout}>
          <Icon name="logout" size={15} />
          <span>Logout</span>
        </button>
      </div>
    </header>
  )
}

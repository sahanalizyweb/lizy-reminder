import { useState } from 'react'
import { Navigate, Outlet, matchPath, useLocation } from 'react-router-dom'
import Header from '../components/Header'
import Sidebar from '../components/Sidebar'
import { useAuth } from '../hooks/useAuth'

const TITLES = [
  ['/reminders/new', 'Add Reminder'],
  ['/reminders/:id/edit', 'Edit Reminder'],
  ['/reminders', 'All Reminders'],
  ['/users/new', 'Add User'],
  ['/users/:id/edit', 'Edit User'],
  ['/users', 'Manage Users'],
  ['/', 'Dashboard'],
]

/** Sidebar + header + page area for signed-in users; everyone else goes to the login page. */
export default function AppLayout() {
  const { user, loading } = useAuth()
  const location = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)

  if (loading) return <div className="splash">Loading…</div>
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />

  const title = TITLES.find(([pattern]) => matchPath({ path: pattern, end: true }, location.pathname))?.[1] ?? 'Lizy Reminder'

  return (
    <div className="app">
      <Sidebar open={menuOpen} onClose={() => setMenuOpen(false)} />
      <div className="main">
        <Header title={title} onMenu={() => setMenuOpen(true)} />
        <main className="page">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

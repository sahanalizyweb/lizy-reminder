import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { ROLE_ADMIN } from '../utils/constants'

/** Gates the Manage Users pages: a User is sent back to the Dashboard. */
export default function RequireAdmin() {
  const { user } = useAuth()

  if (user?.role !== ROLE_ADMIN) return <Navigate to="/" replace />

  return <Outlet />
}

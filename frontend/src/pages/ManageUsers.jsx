import { useState } from 'react'
import { Link } from 'react-router-dom'
import ConfirmDialog from '../components/ConfirmDialog'
import StatusBadge from '../components/StatusBadge'
import { useAuth } from '../hooks/useAuth'
import { useToast } from '../hooks/useToast'
import { useUsers } from '../hooks/useUsers'
import { userService } from '../services/userService'

/** Admin only: every login account and its role. */
export default function ManageUsers() {
  const { user: self } = useAuth()
  const toast = useToast()
  const { users, loading, error, reload } = useUsers()
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await userService.remove(deleting.id)
      toast.success(`${deleting.name}'s account was deleted.`)
      setDeleting(null)
      reload()
    } catch (err) {
      toast.error(err.message)
      setDeleting(null)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <h2>Manage Users</h2>
          <p className="muted">{users.length ? `${users.length} account${users.length === 1 ? '' : 's'}` : loading ? 'Loading…' : 'No accounts yet.'}</p>
        </div>
        <Link to="/users/new" className="btn btn--primary">
          Add User
        </Link>
      </div>

      {error && (
        <div className="alert alert--error" role="alert">
          {error.message}{' '}
          <button type="button" className="link-btn" onClick={reload}>
            Try again
          </button>
        </div>
      )}

      <div className={`table-scroll ${loading ? 'is-loading' : ''}`} tabIndex={0} aria-busy={loading}>
        <table className="reminder-table">
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Email</th>
              <th scope="col">Role</th>
              <th scope="col" className="col-action">
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            {users.map((account) => (
              <tr key={account.id}>
                <td className="strong">
                  {account.name}
                  {account.id === self.id && <span className="muted"> (you)</span>}
                </td>
                <td>{account.email}</td>
                <td>
                  <StatusBadge status={account.role === 'admin' ? 'Admin' : 'User'} />
                </td>
                <td className="col-action">
                  <div className="row-actions">
                    <Link className="btn btn--sm btn--ghost" to={`/users/${account.id}/edit`}>
                      Edit
                    </Link>
                    <button
                      type="button"
                      className="btn btn--sm btn--danger-outline"
                      disabled={account.id === self.id}
                      onClick={() => setDeleting(account)}
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            ))}
            {users.length === 0 && (
              <tr className="empty-row">
                <td colSpan={4}>{loading ? 'Loading users…' : 'No accounts found.'}</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {deleting && (
        <ConfirmDialog
          title="Delete account?"
          message={`Delete ${deleting.name}'s login (${deleting.email})? This cannot be undone.`}
          confirmLabel="Delete"
          danger
          busy={busy}
          onConfirm={confirmDelete}
          onCancel={() => setDeleting(null)}
        />
      )}
    </div>
  )
}

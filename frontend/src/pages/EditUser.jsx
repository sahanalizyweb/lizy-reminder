import { useNavigate, useParams } from 'react-router-dom'
import UserForm from '../components/UserForm'
import { useApi } from '../hooks/useApi'
import { useToast } from '../hooks/useToast'
import { userService } from '../services/userService'

export default function EditUser() {
  const { id } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const { data: account, loading, error } = useApi(userService.get, id)

  const save = async (payload) => {
    await userService.update(id, payload)
    toast.success('Account updated.')
    navigate('/users')
  }

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <h2>Edit User</h2>
        </div>
      </div>

      {error && (
        <div className="alert alert--error" role="alert">
          {error.status === 404 ? 'This account no longer exists.' : error.message}
        </div>
      )}
      {loading && !account && <p className="muted">Loading…</p>}
      {account && <UserForm key={account.id} account={account} submitLabel="Save Changes" onSubmit={save} onCancel={() => navigate('/users')} />}
    </div>
  )
}

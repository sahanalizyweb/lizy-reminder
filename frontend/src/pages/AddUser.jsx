import { useNavigate } from 'react-router-dom'
import UserForm from '../components/UserForm'
import { useToast } from '../hooks/useToast'
import { userService } from '../services/userService'

export default function AddUser() {
  const navigate = useNavigate()
  const toast = useToast()

  const save = async (payload) => {
    const account = await userService.create(payload)
    toast.success(`${account.name}'s account was created.`)
    navigate('/users')
  }

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <h2>Add User</h2>
          <p className="muted">Admin accounts see and manage every reminder.</p>
        </div>
      </div>
      <UserForm submitLabel="Save User" onSubmit={save} onCancel={() => navigate(-1)} />
    </div>
  )
}

import { useLocation, useNavigate, useParams } from 'react-router-dom'
import ReminderForm from '../components/ReminderForm'
import { useApi } from '../hooks/useApi'
import { useAuth } from '../hooks/useAuth'
import { useProductCategories } from '../hooks/useProductCategories'
import { useStaff } from '../hooks/useStaff'
import { useToast } from '../hooks/useToast'
import { reminderService } from '../services/reminderService'
import { ROLE_USER } from '../utils/constants'

export default function EditReminder() {
  const { id } = useParams()
  const navigate = useNavigate()
  const location = useLocation()
  const toast = useToast()
  const { user } = useAuth()
  const { staff } = useStaff()
  const { categories } = useProductCategories()
  const { data: reminder, loading, error } = useApi(reminderService.get, id)
  const lockedAssignedPerson = user.role === ROLE_USER ? { id: user.assigned_person_id, name: user.assigned_person_name } : null

  const back = () => navigate(location.state?.from ?? '/reminders')

  const save = async (payload) => {
    await reminderService.update(id, payload)
    toast.success(`Reminder #${id} updated.`)
    back()
  }

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <h2>Edit Reminder #{id}</h2>
        </div>
      </div>

      {error && (
        <div className="alert alert--error" role="alert">
          {error.status === 404 && 'This reminder no longer exists.'}
          {error.status === 403 && 'You do not have access to this reminder.'}
          {error.status !== 404 && error.status !== 403 && error.message}
        </div>
      )}
      {loading && !reminder && <p className="muted">Loading…</p>}
      {reminder && (
        <ReminderForm
          key={reminder.id}
          reminder={reminder}
          staff={staff}
          categories={categories}
          lockedAssignedPerson={lockedAssignedPerson}
          submitLabel="Save Changes"
          onSubmit={save}
          onCancel={back}
        />
      )}
    </div>
  )
}

import { useNavigate } from 'react-router-dom'
import ReminderForm from '../components/ReminderForm'
import { useAuth } from '../hooks/useAuth'
import { useToast } from '../hooks/useToast'
import { useProductCategories } from '../hooks/useProductCategories'
import { useStaff } from '../hooks/useStaff'
import { reminderService } from '../services/reminderService'
import { ROLE_USER, VIEWS, VIEW_FOR_STATUS } from '../utils/constants'

export default function AddReminder() {
  const navigate = useNavigate()
  const toast = useToast()
  const { user } = useAuth()
  const { staff } = useStaff()
  const { categories } = useProductCategories()
  const lockedAssignedPerson = user.role === ROLE_USER ? { id: user.assigned_person_id, name: user.assigned_person_name } : null

  const save = async (payload) => {
    const reminder = await reminderService.create(payload)
    // Travel / Real Estate reminders don't have a calculated Today/Upcoming/Overdue
    // status (theirs is picked by hand, see Reminder::MANUAL_STATUS_TYPES) — for
    // those, VIEW_FOR_STATUS has no matching dashboard tab to jump to.
    const view = VIEW_FOR_STATUS[reminder.status]
    if (view) {
      toast.success(`Reminder #${reminder.id} added. It is listed under ${VIEWS[view].label}.`)
      navigate('/', { state: { view } })
    } else {
      toast.success(`Reminder #${reminder.id} added.`)
      navigate('/reminders')
    }
  }

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <h2>Add Reminder</h2>
          <p className="muted">It will appear in Today’s Work, Upcoming or Overdue automatically, based on its dates.</p>
        </div>
      </div>
      <ReminderForm
        staff={staff}
        categories={categories}
        lockedAssignedPerson={lockedAssignedPerson}
        submitLabel="Save Reminder"
        onSubmit={save}
        onCancel={() => navigate(-1)}
      />
    </div>
  )
}

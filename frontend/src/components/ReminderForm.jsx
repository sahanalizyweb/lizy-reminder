import { useState } from 'react'
import { MANUAL_STATUS_OPTIONS, REMINDER_TYPES, TYPE_IT_SERVICE, TYPE_PRODUCT, TYPE_REAL_ESTATE, TYPE_TRAVEL } from '../utils/constants'

const BLANK = {
  reminder_type: '',
  customer_name: '',
  phone: '',
  assigned_to: '',
  scheduled_date: '',
  reminder_date: '',
  notes: '',
  product_name: '',
  product_category_id: '',
  quantity: 1,
  price: '',
  website_link: '',
  booking_name: '',
  property_name: '',
  location: '',
  status: '',
  completed: false,
}

function toFormValues(reminder, lockedAssignedPerson) {
  if (!reminder) return lockedAssignedPerson ? { ...BLANK, assigned_to: lockedAssignedPerson.id } : BLANK
  return {
    reminder_type: reminder.reminder_type,
    customer_name: reminder.customer_name === 'Not Provided' ? '' : reminder.customer_name,
    phone: reminder.phone,
    assigned_to: reminder.assigned_to ?? '',
    scheduled_date: reminder.scheduled_date,
    reminder_date: reminder.reminder_date,
    notes: reminder.notes ?? '',
    product_name: reminder.product_name ?? '',
    product_category_id: reminder.product_category_id ?? '',
    quantity: reminder.quantity ?? 1,
    price: reminder.price ?? '',
    website_link: reminder.website_link ?? '',
    booking_name: reminder.booking_name ?? '',
    property_name: reminder.property_name ?? '',
    location: reminder.location ?? '',
    // For Travel / Real Estate, reminder.status *is* the manually-picked value (see backend Reminder::state()).
    status: reminder.status ?? '',
    completed: reminder.is_completed,
  }
}

function validate(values) {
  const errors = {}
  if (!values.reminder_type) errors.reminder_type = 'Choose a reminder type.'
  if (!values.phone.trim()) errors.phone = 'Enter a phone number.'
  else if (!/^\+?[0-9][0-9\s\-()]{4,}$/.test(values.phone.trim())) errors.phone = 'Enter a valid phone number.'
  if (!values.scheduled_date) errors.scheduled_date = 'Choose the date.'
  if (!values.reminder_date) errors.reminder_date = 'Choose the reminder date.'
  else if (values.scheduled_date && values.reminder_date > values.scheduled_date) {
    errors.reminder_date = 'The reminder date must be on or before that date.'
  }

  if (values.reminder_type === TYPE_PRODUCT) {
    if (!values.product_name.trim()) errors.product_name = 'Enter the product name.'
    if (!values.product_category_id) errors.product_category_id = 'Choose a product category.'
    if (!(Number(values.quantity) >= 1)) errors.quantity = 'Quantity must be at least 1.'
  } else if (values.reminder_type === TYPE_IT_SERVICE) {
    const link = values.website_link.trim()
    if (!link) errors.website_link = 'Enter the website link.'
    else if (!/^(https?:\/\/)?[^\s]+\.[^\s]{2,}/i.test(link)) errors.website_link = 'Enter a valid URL, e.g. https://example.com'
  } else if (values.reminder_type === TYPE_TRAVEL) {
    if (!values.booking_name.trim()) errors.booking_name = 'Enter the booking / tour name.'
    if (!values.status) errors.status = 'Choose a status.'
  } else if (values.reminder_type === TYPE_REAL_ESTATE) {
    if (!values.property_name.trim()) errors.property_name = 'Enter the property / project name.'
    if (!values.location.trim()) errors.location = 'Enter the location.'
    if (!values.status) errors.status = 'Choose a status.'
  }
  return errors
}

// Defined at module level so inputs keep focus while typing (a component created
// inside the form would be remounted on every keystroke).
function Field({ label, required = false, hint, error, children }) {
  return (
    <label className={`field ${error ? 'field--error' : ''}`}>
      <span>
        {label}
        {required && <b className="req"> *</b>}
      </span>
      {children}
      {hint && !error && <small className="hint">{hint}</small>}
      {error && <small className="error-text">{error}</small>}
    </label>
  )
}

/**
 * Add / edit form. Fields shown depend on the reminder type: Product shows
 * Product Name / Category / Quantity / Price, IT Service shows Website Link,
 * Travel / Family Tour Booking shows Booking / Tour Name, Real Estate
 * Marketing shows Property / Project Name + Location. The latter two also
 * show a Status dropdown (Confirmed / Scheduled / Reminder / Processing /
 * Due / Completed / Cancelled) in place of the Completed checkbox the other
 * types use, and label the date field "Travel Date" / "Marketing Follow-up
 * Date" instead of "Scheduled / Due Date" — it's the same underlying field.
 * A reminder saved under an older type (e.g. Hosting) keeps that type as an
 * extra option instead of being forced onto one of the four current types,
 * and shows the Product-style fields (that is the shape it was originally
 * saved in).
 *
 * `lockedAssignedPerson` ({id, name}), when given, means the signed-in
 * account is a User: the Assigned Person field is shown but not editable —
 * it is always that person, and the backend enforces this regardless of
 * what is submitted.
 *
 * `onSubmit(payload)` should return a promise; a Laravel validation error
 * (422) from it is shown under the matching fields.
 */
export default function ReminderForm({ reminder, staff, categories, lockedAssignedPerson, submitLabel, onSubmit, onCancel }) {
  const editing = Boolean(reminder)
  const [values, setValues] = useState(() => toFormValues(reminder, lockedAssignedPerson))
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)

  const legacyType = reminder && !REMINDER_TYPES.includes(reminder.reminder_type) ? reminder.reminder_type : null
  const typeOptions = legacyType ? [legacyType, ...REMINDER_TYPES] : REMINDER_TYPES

  const isProduct = values.reminder_type === TYPE_PRODUCT
  const isItService = values.reminder_type === TYPE_IT_SERVICE
  const isTravel = values.reminder_type === TYPE_TRAVEL
  const isRealEstate = values.reminder_type === TYPE_REAL_ESTATE
  const isManualStatus = isTravel || isRealEstate
  const isLegacySelected = Boolean(legacyType) && values.reminder_type === legacyType
  const showProductFields = isProduct || isLegacySelected
  const dateLabel = isTravel ? 'Travel Date' : isRealEstate ? 'Marketing Follow-up Date' : 'Scheduled / Due Date'

  const set = (name, value) => {
    setValues((current) => ({ ...current, [name]: value }))
    setErrors((current) => ({ ...current, [name]: undefined }))
  }
  const bind = (name) => ({ value: values[name], onChange: (event) => set(name, event.target.value) })

  // The reminder date follows the due date until it is set separately, and can never be later than it.
  const changeScheduledDate = (event) => {
    const scheduled = event.target.value
    setValues((current) => {
      const followsDue = !current.reminder_date || current.reminder_date === current.scheduled_date
      return {
        ...current,
        scheduled_date: scheduled,
        reminder_date: followsDue || current.reminder_date > scheduled ? scheduled : current.reminder_date,
      }
    })
    setErrors((current) => ({ ...current, scheduled_date: undefined, reminder_date: undefined }))
  }

  const submit = async (event) => {
    event.preventDefault()
    setFormError('')

    const found = validate(values)
    setErrors(found)
    if (Object.keys(found).length > 0) return

    const payload = {
      reminder_type: values.reminder_type,
      customer_name: values.customer_name.trim(),
      phone: values.phone.trim(),
      assigned_to: values.assigned_to === '' ? null : Number(values.assigned_to),
      scheduled_date: values.scheduled_date,
      reminder_date: values.reminder_date,
      notes: values.notes.trim() || null,
    }
    if (isProduct) {
      payload.product_name = values.product_name.trim()
      payload.product_category_id = Number(values.product_category_id)
      payload.quantity = Number(values.quantity)
      payload.price = values.price === '' ? null : Number(values.price)
    } else if (isItService) {
      payload.website_link = values.website_link.trim()
    } else if (isTravel) {
      payload.booking_name = values.booking_name.trim()
    } else if (isRealEstate) {
      payload.property_name = values.property_name.trim()
      payload.location = values.location.trim()
    }

    if (isManualStatus) {
      payload.status = values.status
    } else if (editing) {
      payload.status = values.completed ? 'Completed' : 'Pending'
    }

    setSaving(true)
    try {
      await onSubmit(payload)
    } catch (err) {
      const serverErrors = Object.fromEntries(Object.entries(err.errors ?? {}).map(([field, messages]) => [field, messages[0]]))
      setErrors(serverErrors)
      setFormError(Object.keys(serverErrors).length > 0 ? 'Please fix the highlighted fields.' : err.message)
      setSaving(false)
    }
  }

  return (
    <form className="card form" onSubmit={submit} noValidate>
      {formError && (
        <div className="alert alert--error" role="alert">
          {formError}
        </div>
      )}

      <div className="form__grid">
        <Field error={errors.reminder_type} label="Reminder Type" required>
          <select value={values.reminder_type} onChange={(event) => set('reminder_type', event.target.value)}>
            <option value="">Select type</option>
            {typeOptions.map((type) => (
              <option key={type} value={type}>
                {type === legacyType ? `${type} (existing)` : type}
              </option>
            ))}
          </select>
        </Field>

        <Field error={errors.customer_name} label="Customer / Company" hint="Leave blank if not known (saved as “Not Provided”).">
          <input type="text" maxLength={255} {...bind('customer_name')} />
        </Field>

        {showProductFields && (
          <>
            <Field error={errors.product_name} label="Product Name" required={isProduct}>
              <input type="text" maxLength={255} {...bind('product_name')} />
            </Field>
            <Field error={errors.product_category_id} label="Product Category" required={isProduct}>
              <select {...bind('product_category_id')}>
                <option value="">Select category</option>
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </select>
            </Field>
          </>
        )}

        <Field error={errors.phone} label="Phone Number" required>
          <input type="tel" inputMode="tel" placeholder="+91 98765 43210" {...bind('phone')} />
        </Field>

        {showProductFields && (
          <>
            <Field error={errors.quantity} label="Quantity" required={isProduct}>
              <input type="number" min="1" step="1" {...bind('quantity')} />
            </Field>
            <Field error={errors.price} label="Price" hint="Optional.">
              <input type="number" min="0" step="0.01" {...bind('price')} />
            </Field>
          </>
        )}

        {isItService && (
          <Field error={errors.website_link} label="Website Link" required hint="e.g. https://example.com">
            <input type="url" placeholder="https://example.com" {...bind('website_link')} />
          </Field>
        )}

        {isTravel && (
          <Field error={errors.booking_name} label="Booking / Tour Name" required>
            <input type="text" maxLength={255} {...bind('booking_name')} />
          </Field>
        )}

        {isRealEstate && (
          <>
            <Field error={errors.property_name} label="Property / Project Name" required>
              <input type="text" maxLength={255} {...bind('property_name')} />
            </Field>
            <Field error={errors.location} label="Location" required>
              <input type="text" maxLength={255} {...bind('location')} />
            </Field>
          </>
        )}

        {!values.reminder_type && (
          <p className="form-note">Choose a reminder type above to continue.</p>
        )}

        {lockedAssignedPerson ? (
          <Field label="Assigned Person" required hint="Your account is linked to this person and cannot be changed.">
            <input type="text" value={lockedAssignedPerson.name} disabled />
          </Field>
        ) : (
          <Field error={errors.assigned_to} label="Assigned Person" required hint="Choose “Unassigned” if no one is assigned yet.">
            <select {...bind('assigned_to')}>
              <option value="">Unassigned</option>
              {staff.map((person) => (
                <option key={person.id} value={person.id}>
                  {person.name}
                </option>
              ))}
            </select>
          </Field>
        )}

        <Field error={errors.scheduled_date} label={dateLabel} required>
          <input type="date" value={values.scheduled_date} onChange={changeScheduledDate} />
        </Field>

        <Field error={errors.reminder_date} label="Reminder Date" required hint="The day the team should follow up.">
          <input type="date" max={values.scheduled_date || undefined} {...bind('reminder_date')} />
        </Field>

        {isManualStatus && (
          <Field error={errors.status} label="Status" required>
            <select {...bind('status')}>
              <option value="">Select status</option>
              {MANUAL_STATUS_OPTIONS.map((status) => (
                <option key={status} value={status}>
                  {status}
                </option>
              ))}
            </select>
          </Field>
        )}

        <Field error={errors.notes} label="Notes">
          <textarea rows={4} maxLength={5000} {...bind('notes')} />
        </Field>

        {editing && !isManualStatus && (
          <label className="check">
            <input type="checkbox" checked={values.completed} onChange={(event) => set('completed', event.target.checked)} />
            <span>Completed</span>
          </label>
        )}
      </div>

      <div className="form__actions">
        <button type="button" className="btn btn--ghost" onClick={onCancel} disabled={saving}>
          Cancel
        </button>
        <button type="submit" className="btn btn--primary" disabled={saving}>
          {saving ? 'Saving…' : submitLabel}
        </button>
      </div>
    </form>
  )
}

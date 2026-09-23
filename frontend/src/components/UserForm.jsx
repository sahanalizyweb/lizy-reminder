import { useState } from 'react'
import { ROLE_ADMIN, ROLE_USER } from '../utils/constants'

const BLANK = {
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: ROLE_USER,
}

function toFormValues(account) {
  if (!account) return BLANK
  return {
    name: account.name,
    email: account.email,
    password: '',
    password_confirmation: '',
    role: account.role,
  }
}

function validate(values, editing) {
  const errors = {}
  if (!values.name.trim()) errors.name = 'Enter a name.'
  if (!values.email.trim()) errors.email = 'Enter an email address.'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email.trim())) errors.email = 'Enter a valid email address.'

  // A new account always sets a password; an existing one only when New Password is filled in.
  const changingPassword = !editing || values.password.trim() !== ''

  if (!editing && !values.password.trim()) errors.password = 'Enter a password.'
  else if (values.password && values.password.length < 6) errors.password = 'Password must be at least 6 characters.'

  if (changingPassword) {
    if (!values.password_confirmation.trim()) errors.password_confirmation = 'Confirm the new password.'
    else if (values.password !== values.password_confirmation) errors.password_confirmation = 'Passwords do not match.'
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
 * Add / edit a login account. Fields: Name, Email, Role, New Password,
 * Confirm Password — that's all, for both Admin and User accounts.
 *
 * Password fields: New Password + Confirm Password are required and must
 * match when creating a new account, or optional (leave both blank to keep
 * the current password) when editing one — but if New Password is filled
 * in, Confirm Password becomes required too. That's the only confirmation
 * needed to change a password.
 *
 * `onSubmit(payload)` should return a promise; a Laravel validation error
 * (422) from it is shown under the matching fields.
 */
export default function UserForm({ account, submitLabel, onSubmit, onCancel }) {
  const editing = Boolean(account)
  const [values, setValues] = useState(() => toFormValues(account))
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)

  const changingPassword = !editing || values.password.trim() !== ''

  const set = (name, value) => {
    setValues((current) => ({ ...current, [name]: value }))
    setErrors((current) => ({ ...current, [name]: undefined }))
  }
  const bind = (name) => ({ value: values[name], onChange: (event) => set(name, event.target.value) })

  const submit = async (event) => {
    event.preventDefault()
    setFormError('')

    const found = validate(values, editing)
    setErrors(found)
    if (Object.keys(found).length > 0) return

    const payload = {
      name: values.name.trim(),
      email: values.email.trim(),
      role: values.role,
    }
    if (changingPassword) {
      payload.password = values.password.trim()
      payload.password_confirmation = values.password_confirmation.trim()
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
        <Field error={errors.name} label="Name" required>
          <input type="text" maxLength={255} {...bind('name')} />
        </Field>

        <Field error={errors.email} label="Email" required>
          <input type="email" maxLength={255} {...bind('email')} />
        </Field>

        <Field
          error={errors.password}
          label="New Password"
          required={!editing}
          hint={editing ? 'Leave blank to keep the current password.' : undefined}
        >
          <input type="password" autoComplete="new-password" {...bind('password')} />
        </Field>

        <Field error={errors.password_confirmation} label="Confirm Password" required={changingPassword}>
          <input type="password" autoComplete="new-password" {...bind('password_confirmation')} />
        </Field>

        <Field error={errors.role} label="Role" required>
          <select {...bind('role')}>
            <option value={ROLE_USER}>User</option>
            <option value={ROLE_ADMIN}>Admin</option>
          </select>
        </Field>
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

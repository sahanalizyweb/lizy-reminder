export const TYPE_PRODUCT = 'Product'
export const TYPE_IT_SERVICE = 'IT Service'
export const TYPE_TRAVEL = 'Travel / Family Tour Booking'
export const TYPE_REAL_ESTATE = 'Real Estate Marketing'

export const ROLE_ADMIN = 'admin'
export const ROLE_USER = 'user'

export const ROLES = [ROLE_ADMIN, ROLE_USER]

export const REMINDER_TYPES = [TYPE_PRODUCT, TYPE_IT_SERVICE, TYPE_TRAVEL, TYPE_REAL_ESTATE]

// Display-only relabelling: what's shown to people is never what's stored/submitted/filtered
// by (still the full TYPE_TRAVEL etc. string) — just the label text wherever a type is shown.
const TYPE_LABELS = {
  [TYPE_TRAVEL]: 'Tour Booking',
}

export function reminderTypeLabel(type) {
  return TYPE_LABELS[type] ?? type
}

// Travel and Real Estate reminders pick their status by hand from this fixed
// list instead of it being calculated from the dates (see the backend's
// Reminder::MANUAL_STATUS_TYPES / MANUAL_STATUSES).
export const MANUAL_STATUS_TYPES = [TYPE_TRAVEL, TYPE_REAL_ESTATE]
export const MANUAL_STATUS_OPTIONS = ['Confirmed', 'Scheduled', 'Reminder', 'Processing', 'Due', 'Completed', 'Cancelled']

export const STATUS_OPTIONS = [
  'Pending', 'Today', 'Upcoming', 'Completed', 'Overdue', 'Expired',
  'Confirmed', 'Scheduled', 'Reminder', 'Processing', 'Due', 'Cancelled',
]

export const RANGE_OPTIONS = [
  { value: '', label: 'All Upcoming' },
  { value: 'today', label: 'Today' },
  { value: 'tomorrow', label: 'Tomorrow' },
  { value: 'next7', label: 'Next 7 Days' },
  { value: 'next30', label: 'Next 30 Days' },
  { value: 'custom', label: 'Custom Date' },
]

// The dashboard views. `columns` picks the table layout.
export const VIEWS = {
  today: { label: "Today's Work", description: 'Reminders due today', columns: 'standard' },
  upcoming: { label: 'Upcoming', description: 'Reminders coming up', columns: 'standard' },
  overdue: { label: 'Overdue / Expired', description: 'Past their date and not completed', columns: 'overdue' },
  completed: { label: 'Completed', description: 'Reminders that have been completed', columns: 'standard' },
}

// Which dashboard view a reminder with a given (calculated) status belongs to.
export const VIEW_FOR_STATUS = {
  Today: 'today',
  Upcoming: 'upcoming',
  Overdue: 'overdue',
  Expired: 'overdue',
  Completed: 'completed',
}

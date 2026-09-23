import { api } from './api'

// The notification bell: reminders whose Reminder Date is today and are not
// completed. Scoped server-side to the signed-in user (Admin sees every
// reminder; a User only their own). Opening a notification marks it read —
// stored in the database, not just locally — which is what drops it out of
// the count; it never changes the reminder itself.
export const notificationService = {
  list: (options) => api.get('/notifications', undefined, options),
  markRead: (reminderId) => api.post(`/notifications/${reminderId}/read`),
}

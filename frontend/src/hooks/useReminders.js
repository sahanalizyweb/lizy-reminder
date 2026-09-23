import { reminderService } from '../services/reminderService'
import { useApi } from './useApi'

/** A page of reminders. `params` are sent to the API as-is (filters, page, per_page). */
export function useReminders(params) {
  const { data, ...rest } = useApi(reminderService.list, params)
  return { rows: data?.data ?? [], meta: data?.meta ?? null, ...rest }
}

/** Dashboard counts: today, upcoming, overdue, completed and the server's date. */
export function useSummary(params) {
  const { data, ...rest } = useApi(reminderService.summary, params)
  return { summary: data, ...rest }
}

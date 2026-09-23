import { staffService } from '../services/staffService'
import { useApi } from './useApi'

/** Staff from the users table (for the assigned-person filter and form). */
export function useStaff() {
  const { data, loading, error } = useApi(staffService.list, null)
  return { staff: data ?? [], loading, error }
}

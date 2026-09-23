import { userService } from '../services/userService'
import { useApi } from './useApi'

/** Every login account (Manage Users, Admin only). */
export function useUsers() {
  const { data, ...rest } = useApi(userService.list, null)
  return { users: data ?? [], ...rest }
}

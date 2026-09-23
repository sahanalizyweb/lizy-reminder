import { useCallback, useEffect, useRef, useState } from 'react'
import { notificationService } from '../services/notificationService'

const POLL_INTERVAL_MS = 60000

/**
 * The notification bell's count and list (today's reminders only). Loads
 * once, then polls so the badge stays current from the database without a
 * manual refresh.
 */
export function useNotifications() {
  const [state, setState] = useState({ count: 0, items: [], loading: true })
  const mounted = useRef(true)

  const load = useCallback(() => {
    notificationService
      .list()
      .then((data) => mounted.current && setState({ count: data.count, items: data.items, loading: false }))
      .catch(() => mounted.current && setState((current) => ({ ...current, loading: false })))
  }, [])

  useEffect(() => {
    mounted.current = true
    load()
    const id = setInterval(load, POLL_INTERVAL_MS)
    return () => {
      mounted.current = false
      clearInterval(id)
    }
  }, [load])

  // Removes it from the badge/list straight away (before the request even
  // resolves); if the request fails, reload() re-syncs with the database.
  const markRead = useCallback(
    (id) => {
      setState((current) => ({
        ...current,
        count: Math.max(0, current.count - 1),
        items: current.items.filter((item) => item.id !== id),
      }))
      notificationService.markRead(id).catch(load)
    },
    [load],
  )

  return { ...state, reload: load, markRead }
}

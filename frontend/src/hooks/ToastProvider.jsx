import { useCallback, useMemo, useRef, useState } from 'react'
import { ToastContext } from './toastContext'

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])
  const nextId = useRef(1)

  const dismiss = useCallback((id) => setToasts((list) => list.filter((toast) => toast.id !== id)), [])

  const push = useCallback(
    (type, message) => {
      const id = nextId.current++
      setToasts((list) => [...list, { id, type, message }])
      setTimeout(() => dismiss(id), 5000)
    },
    [dismiss],
  )

  const toast = useMemo(
    () => ({ success: (message) => push('success', message), error: (message) => push('error', message) }),
    [push],
  )

  return (
    <ToastContext.Provider value={toast}>
      {children}
      <div className="toasts" role="status" aria-live="polite">
        {toasts.map((item) => (
          <div key={item.id} className={`toast toast--${item.type}`} onClick={() => dismiss(item.id)}>
            {item.message}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

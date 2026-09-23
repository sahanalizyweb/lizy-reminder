import { useCallback, useEffect, useState } from 'react'

/**
 * Runs `fetcher(params, { signal })` whenever `params` change (or `reload()` is
 * called) and keeps the latest result. A superseded request is aborted, so a
 * slow old response can never overwrite a newer one. The previous data stays
 * visible while a refetch is in flight.
 */
export function useApi(fetcher, params) {
  const key = JSON.stringify(params ?? null)
  const [version, setVersion] = useState(0)
  const requestKey = `${key}#${version}`
  const [result, setResult] = useState({ data: null, error: null, settledKey: null })

  useEffect(() => {
    const controller = new AbortController()

    fetcher(JSON.parse(key), { signal: controller.signal })
      .then((data) => setResult({ data, error: null, settledKey: requestKey }))
      .catch((error) => {
        if (error.name !== 'AbortError') setResult((current) => ({ ...current, error, settledKey: requestKey }))
      })

    return () => controller.abort()
  }, [fetcher, key, requestKey])

  const reload = useCallback(() => setVersion((value) => value + 1), [])
  const settled = result.settledKey === requestKey

  return { data: result.data, error: settled ? result.error : null, loading: !settled, reload }
}

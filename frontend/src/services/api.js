const BASE_URL = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '')
const TOKEN_KEY = 'lizy_reminder_token'

export const AUTH_EXPIRED_EVENT = 'lizy:auth-expired'

export const tokenStore = {
  get() {
    try {
      return localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set(token) {
    try {
      localStorage.setItem(TOKEN_KEY, token)
    } catch {
      /* storage unavailable: the user just has to log in again next time */
    }
  },
  clear() {
    try {
      localStorage.removeItem(TOKEN_KEY)
    } catch {
      /* ignore */
    }
  },
}

export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors // Laravel validation errors: { field: [messages] }
  }
}

function buildUrl(path, params) {
  const url = new URL(`${BASE_URL}${path}`, window.location.origin)
  for (const [key, value] of Object.entries(params ?? {})) {
    if (value !== '' && value != null) url.searchParams.set(key, value)
  }
  return url
}

async function request(path, { method = 'GET', params, body, signal } = {}) {
  const headers = { Accept: 'application/json' }
  const token = tokenStore.get()
  if (token) headers.Authorization = `Bearer ${token}`
  if (body !== undefined) headers['Content-Type'] = 'application/json'

  let response
  try {
    response = await fetch(buildUrl(path, params), {
      method,
      headers,
      signal,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    })
  } catch (error) {
    if (error.name === 'AbortError') throw error
    throw new ApiError('Cannot reach the server. Check that the Laravel API is running.', 0)
  }

  if (response.status === 204) return null

  let payload = null
  try {
    payload = await response.json()
  } catch {
    /* non-JSON body */
  }

  if (!response.ok) {
    if (response.status === 401 && path !== '/login') {
      tokenStore.clear()
      window.dispatchEvent(new Event(AUTH_EXPIRED_EVENT))
    }
    throw new ApiError(payload?.message || `Request failed (${response.status})`, response.status, payload?.errors)
  }

  return payload
}

/** Like request(), but for a binary file download (e.g. the Excel export) instead of JSON. */
async function requestBlob(path, { params, signal } = {}) {
  const headers = { Accept: '*/*' }
  const token = tokenStore.get()
  if (token) headers.Authorization = `Bearer ${token}`

  let response
  try {
    response = await fetch(buildUrl(path, params), { headers, signal })
  } catch (error) {
    if (error.name === 'AbortError') throw error
    throw new ApiError('Cannot reach the server. Check that the Laravel API is running.', 0)
  }

  if (!response.ok) {
    if (response.status === 401) {
      tokenStore.clear()
      window.dispatchEvent(new Event(AUTH_EXPIRED_EVENT))
    }
    let message = `Request failed (${response.status})`
    try {
      message = (await response.json())?.message || message
    } catch {
      /* non-JSON error body */
    }
    throw new ApiError(message, response.status)
  }

  const disposition = response.headers.get('Content-Disposition') || ''
  const filename = disposition.match(/filename="?([^";]+)"?/)?.[1] ?? null

  return { blob: await response.blob(), filename }
}

/** Triggers a browser "Save As" download for a blob, without navigating away from the page. */
function saveBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

export const api = {
  get: (path, params, options) => request(path, { ...options, params }),
  post: (path, body, options) => request(path, { ...options, method: 'POST', body: body ?? {} }),
  put: (path, body, options) => request(path, { ...options, method: 'PUT', body }),
  delete: (path, options) => request(path, { ...options, method: 'DELETE' }),
  download: async (path, params, fallbackFilename) => {
    const { blob, filename } = await requestBlob(path, { params })
    saveBlob(blob, filename ?? fallbackFilename)
  },
}

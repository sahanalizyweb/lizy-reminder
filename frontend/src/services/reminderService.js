import { api } from './api'

// Every list/summary filter is sent to Laravel and applied in MySQL.
export const reminderService = {
  list: (params, options) => api.get('/reminders', params, options),
  summary: (params, options) => api.get('/reminders/summary', params, options),
  get: async (id, options) => (await api.get(`/reminders/${id}`, undefined, options)).data,
  create: async (data) => (await api.post('/reminders', data)).data,
  update: async (id, data) => (await api.put(`/reminders/${id}`, data)).data,
  complete: async (id) => (await api.post(`/reminders/${id}/complete`)).data,
  remove: (id) => api.delete(`/reminders/${id}`),
  history: async (id, options) => (await api.get(`/reminders/${id}/history`, undefined, options)).data,
  // Downloads every reminder matching `params` (the currently applied filters) as an .xlsx file.
  export: (params) => api.download('/reminders/export', params, 'lizy-reminders.xlsx'),
}

import { api } from './api'

// Manage Users (Admin only). Not to be confused with staffService, which is
// the read-only assignable-staff list used by the Assigned Person dropdowns.
export const userService = {
  list: async (params, options) => (await api.get('/manage-users', params, options)).data,
  get: async (id, options) => (await api.get(`/manage-users/${id}`, undefined, options)).data,
  create: async (data) => (await api.post('/manage-users', data)).data,
  update: async (id, data) => (await api.put(`/manage-users/${id}`, data)).data,
  remove: (id) => api.delete(`/manage-users/${id}`),
}

import { api } from './api'

export const staffService = {
  list: async (params, options) => (await api.get('/users', params, options)).data,
}

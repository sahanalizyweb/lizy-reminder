import { api } from './api'

export const productCategoryService = {
  list: async (params, options) => (await api.get('/product-categories', params, options)).data,
}

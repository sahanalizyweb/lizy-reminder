import { productCategoryService } from '../services/productCategoryService'
import { useApi } from './useApi'

/** Product categories from the database (for the Product form and table). */
export function useProductCategories() {
  const { data, loading, error } = useApi(productCategoryService.list, null)
  return { categories: data ?? [], loading, error }
}

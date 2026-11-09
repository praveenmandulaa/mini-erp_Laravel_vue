
import api from './api'

export const productService = {
  getAll(params = {}) {
    return api.get('/products', { params })
  },

  getById(id) {
    return api.get(`/products/${id}`)
  },

  create(data) {
    return api.post('/products', data)
  },

  update(id, data) {
    return api.put(`/products/${id}`, data)
  },

  remove(id) {
    return api.delete(`/products/${id}`)
  },
}

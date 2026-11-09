
import api from './api'

export const orderService = {
  getSuppliers() {
    return api.get('/suppliers')
  },

  getProducts() {
    return api.get('/products', {
      params: { per_page: 100 },
    })
  },

  getOrders(type, page = 1) {
    const endpoint = type === 'purchase'
      ? '/purchase-orders'
      : '/sales-orders'

    return api.get(endpoint, { params: { page } })
  },

  createOrder(type, payload) {
    const endpoint = type === 'purchase'
      ? '/purchase-orders'
      : '/sales-orders'

    return api.post(endpoint, payload)
  },

  completeOrder(type, id) {
    const endpoint = type === 'purchase'
      ? `/purchase-orders/${id}/receive`
      : `/sales-orders/${id}/confirm`

    return api.post(endpoint)
  },
}

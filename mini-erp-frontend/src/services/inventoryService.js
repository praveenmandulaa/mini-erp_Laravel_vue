import api from './api'

export const inventoryService = {
  getSummary() {
    return api.get('/inventory/summary')
  },

  getMovements(params = {}) {
    return api.get('/inventory/movements', { params })
  },

  getLowStockProducts(params = {}) {
    return api.get('/inventory/low-stock', { params })
  },
}

<script setup>
import { onMounted, ref } from 'vue'
import { inventoryService } from '../services/inventoryService'

const summary = ref(null)
const lowStock = ref([])
const movements = ref([])

const loading = ref(false)
const error = ref('')

const movementPage = ref(1)
const movementLastPage = ref(1)

const lowStockPage = ref(1)
const lowStockLastPage = ref(1)

const movementType = ref('')

const cards = [
  ['total_products', 'Total Products'],
  ['active_products', 'Active Products'],
  ['total_stock_units', 'Stock Units'],
  ['low_stock_products', 'Low Stock Products'],
  ['draft_purchase_orders', 'Draft Purchases'],
  ['received_purchase_orders', 'Received Purchases'],
  ['draft_sales_orders', 'Draft Sales'],
  ['confirmed_sales_orders', 'Confirmed Sales'],
]

const fetchSummary = async () => {
  const response = await inventoryService.getSummary()
  summary.value = response.data.data
}

const fetchLowStock = async () => {
  const response = await inventoryService.getLowStockProducts({
    page: lowStockPage.value,
    per_page: 5,
  })

  lowStock.value = response.data.data
  lowStockLastPage.value = response.data.last_page
}

const fetchMovements = async () => {
  const response = await inventoryService.getMovements({
    page: movementPage.value,
    per_page: 10,
    ...(movementType.value ? { type: movementType.value } : {}),
  })

  movements.value = response.data.data
  movementLastPage.value = response.data.last_page
}

const refreshDashboard = async () => {
  loading.value = true
  error.value = ''

  try {
    await Promise.all([
      fetchSummary(),
      fetchLowStock(),
      fetchMovements(),
    ])
  } catch (err) {
    console.error(err)
    error.value = err.response?.data?.message ||
      'Failed to load dashboard data'
  } finally {
    loading.value = false
  }
}

const changeMovementPage = async page => {
  if (page < 1 || page > movementLastPage.value) return

  movementPage.value = page

  try {
    await fetchMovements()
  } catch (err) {
    error.value = 'Unable to load stock movements'
  }
}

const changeLowStockPage = async page => {
  if (page < 1 || page > lowStockLastPage.value) return

  lowStockPage.value = page

  try {
    await fetchLowStock()
  } catch (err) {
    error.value = 'Unable to load low-stock products'
  }
}

const filterMovements = async () => {
  movementPage.value = 1

  try {
    await fetchMovements()
  } catch (err) {
    error.value = 'Unable to filter stock movements'
  }
}

const formatDate = value =>
  value ? new Date(value).toLocaleString('en-IN') : '-'

onMounted(refreshDashboard)

defineExpose({ refreshDashboard })
</script>

<template>
  <section class="dashboard">
    <div class="section-header">
      <div>
        <h2>ERP Dashboard</h2>
        <p>Inventory and order overview</p>
      </div>

      <button
        :disabled="loading"
        @click="refreshDashboard"
      >
        {{ loading ? 'Refreshing...' : 'Refresh Dashboard' }}
      </button>
    </div>

    <p v-if="error" class="error">{{ error }}</p>

    <div class="kpi-grid">
      <div
        v-for="[key, label] in cards"
        :key="key"
        class="kpi-card"
      >
        <span>{{ label }}</span>
        <strong>{{ summary?.[key] ?? '—' }}</strong>
      </div>
    </div>

    <div class="panel">
      <h3>Low Stock Products</h3>

      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Product</th>
              <th>SKU</th>
              <th>Available</th>
              <th>Reorder Level</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="product in lowStock" :key="product.id">
              <td>{{ product.name }}</td>
              <td>{{ product.sku }}</td>
              <td class="low">{{ product.stock_quantity }}</td>
              <td>{{ product.reorder_level }}</td>
            </tr>

            <tr v-if="!lowStock.length">
              <td colspan="4">No low-stock products found</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <button
          :disabled="lowStockPage <= 1"
          @click="changeLowStockPage(lowStockPage - 1)"
        >
          Previous
        </button>

        <span>Page {{ lowStockPage }} of {{ lowStockLastPage }}</span>

        <button
          :disabled="lowStockPage >= lowStockLastPage"
          @click="changeLowStockPage(lowStockPage + 1)"
        >
          Next
        </button>
      </div>
    </div>

    <div class="panel">
      <div class="section-header">
        <h3>Stock Movement History</h3>

        <select v-model="movementType" @change="filterMovements">
          <option value="">All Movements</option>
          <option value="IN">Stock IN</option>
          <option value="OUT">Stock OUT</option>
        </select>
      </div>

      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Product</th>
              <th>Type</th>
              <th>Quantity</th>
              <th>Before</th>
              <th>After</th>
              <th>Reference</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="movement in movements" :key="movement.id">
              <td>{{ formatDate(movement.created_at) }}</td>
              <td>{{ movement.product?.name ?? 'Unknown' }}</td>
              <td>
                <span
                  class="movement-badge"
                  :class="movement.type === 'IN' ? 'in' : 'out'"
                >
                  {{ movement.type }}
                </span>
              </td>
              <td>{{ movement.quantity }}</td>
              <td>{{ movement.stock_before }}</td>
              <td>{{ movement.stock_after }}</td>
              <td>
                {{ movement.reference_type }}
                #{{ movement.reference_id }}
              </td>
            </tr>

            <tr v-if="!movements.length">
              <td colspan="7">No stock movements found</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <button
          :disabled="movementPage <= 1"
          @click="changeMovementPage(movementPage - 1)"
        >
          Previous
        </button>

        <span>
          Page {{ movementPage }} of {{ movementLastPage }}
        </span>

        <button
          :disabled="movementPage >= movementLastPage"
          @click="changeMovementPage(movementPage + 1)"
        >
          Next
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.section-header h2,
.section-header h3 {
  margin: 0;
}

.section-header p {
  color: #64748b;
  margin: 6px 0 0;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.kpi-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.kpi-card span {
  color: #64748b;
  font-size: 14px;
}

.kpi-card strong {
  font-size: 30px;
  color: #0f172a;
}

.panel {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
}

.panel h3 {
  margin-top: 0;
}

.table-scroll {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 14px;
}

th,
td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
}

th {
  background: #f8fafc;
}

.low {
  color: #dc2626;
  font-weight: bold;
}

.movement-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-weight: 600;
}

.in {
  background: #dcfce7;
  color: #166534;
}

.out {
  background: #fee2e2;
  color: #991b1b;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 18px;
  gap: 12px;
}

button,
select {
  padding: 9px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  background: white;
  cursor: pointer;
}

button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.error {
  color: #b91c1c;
  background: #fee2e2;
  padding: 12px;
  border-radius: 8px;
}

@media (max-width: 900px) {
  .kpi-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 520px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }
}
</style>

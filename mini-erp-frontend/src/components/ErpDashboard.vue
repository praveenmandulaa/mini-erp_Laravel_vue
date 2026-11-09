
<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'

const summary = ref({})
const movements = ref([])
const loading = ref(false)
const error = ref('')

const fetchDashboard = async () => {
  loading.value = true
  error.value = ''

  try {
    const [summaryResponse, movementsResponse] =
      await Promise.all([
        api.get('/inventory/summary'),
        api.get('/inventory/movements'),
      ])

    summary.value = summaryResponse.data.data
      ?? summaryResponse.data

    movements.value =
      movementsResponse.data.data?.data
      ?? movementsResponse.data.data
      ?? []
  } catch (err) {
    console.error(err)
    error.value = 'Unable to load dashboard data.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchDashboard)
const formatDate = (value) => {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return '-'

  return date.toLocaleString('en-IN', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}
</script>

<template>
  <section>
    <div class="dashboard-header">
      <div>
        <h2>ERP Dashboard</h2>
        <p>Inventory and order overview</p>
      </div>

      <button @click="fetchDashboard">
        Refresh Dashboard
      </button>
    </div>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="loading">Loading dashboard...</p>

    <template v-else>
      <div class="stats-grid">
        <div
          v-for="stat in [
            ['Total Products', summary.total_products],
            ['Active Products', summary.active_products],
            ['Stock Units', summary.total_stock_units],
            ['Low Stock Products', summary.low_stock_products],
            ['Draft Purchases', summary.draft_purchase_orders],
            ['Received Purchases', summary.received_purchase_orders],
            ['Draft Sales', summary.draft_sales_orders],
            ['Confirmed Sales', summary.confirmed_sales_orders],
          ]"
          :key="stat[0]"
          class="stat-card"
        >
          <span>{{ stat[0] }}</span>
          <strong>{{ stat[1] ?? 0 }}</strong>
        </div>
      </div>

      <div class="panel">
        <h3>Stock Movement History</h3>

        <div class="table-wrap">
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
              <tr
                v-for="movement in movements"
                :key="movement.id"
              >
                <td>{{ formatDate(movement.created_at) }}</td>
                <td>{{ movement.product?.name ?? '-' }}</td>
                <td>{{ movement.type }}</td>
                <td>{{ movement.quantity }}</td>
                <td>{{ movement.stock_before }}</td>
                <td>{{ movement.stock_after }}</td>
                <td>
                  {{ movement.reference_type }}
                  #{{ movement.reference_id }}
                </td>
              </tr>

              <tr v-if="movements.length === 0">
                <td colspan="7">No movements found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.dashboard-header h2 {
  margin: 0;
}

.dashboard-header p {
  color: #64748b;
}

button {
  padding: 10px 14px;
  background: white;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  cursor: pointer;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.stat-card,
.panel {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: white;
  padding: 18px;
}

.stat-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.stat-card span {
  font-size: 13px;
  color: #64748b;
}

.stat-card strong {
  font-size: 26px;
}

.panel {
  margin-top: 20px;
}

.table-wrap {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 10px;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
}

th {
  background: #f8fafc;
}

.error {
  color: #dc2626;
}

@media (max-width: 700px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>

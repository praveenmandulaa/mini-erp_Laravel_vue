
<script setup>
import { ref, onMounted } from 'vue'
import { notificationService } from '../services/notificationService'

const notifications = ref([])
const loading = ref(false)
const error = ref('')

const fetchNotifications = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await notificationService.getAll()
    notifications.value = response.data.data
  } catch (err) {
    console.error(err)
    error.value = 'Unable to load notifications'
  } finally {
    loading.value = false
  }
}

defineExpose({
  fetchNotifications,
})

onMounted(fetchNotifications)
</script>

<template>
  <section class="alerts-panel">
    <div class="alerts-header">
      <h2>Low Stock Alerts</h2>

      <button
        type="button"
        :disabled="loading"
        @click="fetchNotifications"
      >
        {{ loading ? 'Refreshing...' : 'Refresh Alerts' }}
      </button>
    </div>

    <p v-if="loading">Loading alerts...</p>

    <p v-if="error" class="error">{{ error }}</p>

    <p v-if="!loading && !error && notifications.length === 0">
      No low-stock alerts.
    </p>

    <div
      v-for="notification in notifications"
      :key="notification.id"
      class="alert-item"
    >
      <div>
        <strong>{{ notification.product_name }}</strong>

        <p>{{ notification.message }}</p>

        <small>
          SKU: {{ notification.sku }}
          | Stock: {{ notification.stock_quantity }}
          | Reorder Level: {{ notification.reorder_level }}
        </small>
      </div>

      <span class="alert-badge">Low Stock</span>
    </div>
  </section>
</template>

<style scoped>
.alerts-panel {
  margin: 24px 0;
  padding: 20px;
  border: 1px solid #fcd34d;
  border-radius: 10px;
  background: #fffbeb;
}

.alerts-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

h2 {
  margin: 0;
  color: #92400e;
}

button {
  padding: 8px 14px;
  border: 1px solid #d97706;
  border-radius: 6px;
  background: white;
  color: #92400e;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
}

.alert-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 14px;
  margin-top: 10px;
  background: white;
  border: 1px solid #fde68a;
  border-radius: 8px;
}

.alert-item p {
  margin: 6px 0;
}

.alert-item small {
  color: #6b7280;
}

.alert-badge {
  padding: 5px 10px;
  background: #fef3c7;
  color: #92400e;
  border-radius: 999px;
  font-size: 12px;
  white-space: nowrap;
}

.error {
  color: #dc2626;
}
</style>

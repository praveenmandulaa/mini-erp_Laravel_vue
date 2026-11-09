
<script setup>
import {
  ref,
  computed,
  onMounted,
  onUnmounted,
  nextTick,
  watch,
} from 'vue'

import { productService } from './services/productService'
import { authService } from './services/authService'

import LoginForm from './components/LoginForm.vue'
import ProductForm from './components/ProductForm.vue'
import LowStockAlerts from './components/LowStockAlerts.vue'
import OrderManagement from './components/OrderManagement.vue'
import ErpDashboard from './components/ErpDashboard.vue'

const user = ref(null)
const checkingSession = ref(true)
const loggingOut = ref(false)

const isAdmin = computed(() => user.value?.role === 'admin')

const activeTab = ref('dashboard')

const products = ref([])
const search = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalProducts = ref(0)

const loading = ref(false)
const error = ref('')
const successMessage = ref('')

const showForm = ref(false)
const selectedProduct = ref(null)
const deletingId = ref(null)

const alertsRef = ref(null)
const dashboardKey = ref(0)

let searchTimer = null
let requestId = 0

const clearMessages = () => {
  error.value = ''
  successMessage.value = ''
}

const resetProtectedState = () => {
  requestId++

  user.value = null
  products.value = []
  currentPage.value = 1
  lastPage.value = 1
  totalProducts.value = 0
  loading.value = false
  showForm.value = false
  selectedProduct.value = null
  activeTab.value = 'dashboard'
  search.value = ''
  clearMessages()

  clearTimeout(searchTimer)
}

const fetchProducts = async () => {
  if (!user.value) return

  const thisRequest = ++requestId

  loading.value = true
  error.value = ''

  try {
    const response = await productService.getAll({
      search: search.value,
      page: currentPage.value,
    })

    if (thisRequest !== requestId) return

    products.value = response.data.data
    currentPage.value = response.data.current_page
    lastPage.value = response.data.last_page
    totalProducts.value = response.data.total
  } catch (err) {
    if (thisRequest !== requestId) return

    if (err.response?.status === 401) {
      resetProtectedState()
      return
    }

    error.value =
      err.response?.data?.message ||
      'Failed to load products.'
  } finally {
    if (thisRequest === requestId) {
      loading.value = false
    }
  }
}

const initializeSession = async () => {
  checkingSession.value = true

  try {
    user.value = await authService.me()

    if (user.value) {
      await fetchProducts()
    }
  } catch {
    resetProtectedState()
  } finally {
    checkingSession.value = false
  }
}

const handleLoggedIn = async loggedInUser => {
  user.value = loggedInUser
  activeTab.value = 'dashboard'
  clearMessages()
  dashboardKey.value++
  await fetchProducts()
}

const logout = async () => {
  if (loggingOut.value) return

  loggingOut.value = true
  clearMessages()

  try {
    await authService.logout()
    resetProtectedState()
  } catch (err) {
    if (err.response?.status === 401) {
      resetProtectedState()
    } else {
      error.value =
        'Unable to log out. Please try again.'
    }
  } finally {
    loggingOut.value = false
  }
}

watch(search, () => {
  if (!user.value) return

  clearTimeout(searchTimer)
  currentPage.value = 1

  searchTimer = setTimeout(fetchProducts, 400)
})

const changePage = page => {
  if (
    page < 1 ||
    page > lastPage.value ||
    loading.value
  ) return

  clearTimeout(searchTimer)
  currentPage.value = page
  fetchProducts()
}

const openAddForm = () => {
  if (!isAdmin.value) return

  selectedProduct.value = null
  successMessage.value = ''
  showForm.value = true
}

const openEditForm = product => {
  if (!isAdmin.value) return

  selectedProduct.value = { ...product }
  successMessage.value = ''
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  selectedProduct.value = null
}

const handleSaved = async () => {
  const wasEditing = Boolean(selectedProduct.value?.id)

  closeForm()
  await nextTick()

  await alertsRef.value?.fetchNotifications()

  successMessage.value = wasEditing
    ? 'Product updated successfully'
    : 'Product created successfully'

  clearTimeout(searchTimer)

  if (!wasEditing) {
    currentPage.value = 1

    if (search.value !== '') {
      search.value = ''
      return
    }
  }

  await fetchProducts()
  dashboardKey.value++
}

const deleteProduct = async product => {
  if (!isAdmin.value || deletingId.value !== null) {
    return
  }

  if (!window.confirm(
    `Are you sure you want to delete "${product.name}"?`
  )) return

  deletingId.value = product.id
  clearMessages()

  try {
    await productService.remove(product.id)

    successMessage.value =
      `"${product.name}" deleted successfully`

    if (
      products.value.length === 1 &&
      currentPage.value > 1
    ) {
      currentPage.value--
    }

    await fetchProducts()
    dashboardKey.value++
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      'Unable to delete product.'
  } finally {
    deletingId.value = null
  }
}

const handleInventoryChanged = async () => {
  await fetchProducts()
  dashboardKey.value++

  if (activeTab.value === 'products') {
    await nextTick()
    await alertsRef.value?.fetchNotifications()
  }
}

const changeTab = tab => {
  clearTimeout(searchTimer)
  closeForm()
  clearMessages()

  activeTab.value = tab

  if (tab === 'products') {
    fetchProducts()
  }

  if (tab === 'dashboard') {
    dashboardKey.value++
  }
}

onMounted(initializeSession)

onUnmounted(() => {
  clearTimeout(searchTimer)
  requestId++
})
</script>

<template>
  <div v-if="checkingSession" class="loading-screen">
    Checking your session...
  </div>

  <LoginForm
    v-else-if="!user"
    @logged-in="handleLoggedIn"
  />

  <main v-else class="container">
    <header class="header">
      <div>
        <h1>Mini ERP</h1>
        <p class="subtitle">
          Products, Purchases, Sales and Inventory
        </p>
      </div>

      <div class="account">
        <div class="account-details">
          <strong>{{ user.name }}</strong>
          <span>{{ user.role }}</span>
        </div>

        <button
          class="logout-btn"
          :disabled="loggingOut"
          @click="logout"
        >
          {{ loggingOut ? 'Logging out...' : 'Logout' }}
        </button>
      </div>
    </header>

    <nav class="erp-nav" aria-label="ERP modules">
      <button
        v-for="tab in [
          'dashboard',
          'products',
          'purchases',
          'sales',
        ]"
        :key="tab"
        :class="{ active: activeTab === tab }"
        @click="changeTab(tab)"
      >
        {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
      </button>
    </nav>

    <p v-if="error" class="error-banner">
      {{ error }}
    </p>

    <p v-if="successMessage" class="success-banner">
      {{ successMessage }}
    </p>

    <!-- Dashboard -->
    <ErpDashboard
      v-if="activeTab === 'dashboard'"
      :key="dashboardKey"
    />

    <!-- Products -->
    <section v-if="activeTab === 'products'">
      <div class="section-header">
        <h2>Product Management</h2>

        <button
          v-if="isAdmin && !showForm"
          class="primary-btn"
          @click="openAddForm"
        >
          + Add Product
        </button>
      </div>

      <LowStockAlerts ref="alertsRef" />

      <ProductForm
        v-if="showForm && isAdmin"
        :product="selectedProduct"
        @saved="handleSaved"
        @cancel="closeForm"
      />

      <div class="toolbar">
        <input
          v-model="search"
          placeholder="Search by name or SKU..."
          type="text"
        />

        <button @click="fetchProducts">
          Refresh
        </button>
      </div>

      <p v-if="loading">Loading products...</p>

      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>SKU</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Status</th>
              <th v-if="isAdmin">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="product in products"
              :key="product.id"
            >
              <td>{{ product.id }}</td>
              <td>{{ product.name }}</td>
              <td>{{ product.sku }}</td>
              <td>₹{{ product.price }}</td>
              <td>{{ product.stock_quantity }}</td>
              <td>
                {{ product.status ? 'Active' : 'Inactive' }}
              </td>

              <td v-if="isAdmin">
                <div class="row-actions">
                  <button
                    class="edit-btn"
                    @click="openEditForm(product)"
                  >
                    Edit
                  </button>

                  <button
                    class="delete-btn"
                    :disabled="deletingId !== null"
                    @click="deleteProduct(product)"
                  >
                    {{
                      deletingId === product.id
                        ? 'Deleting...'
                        : 'Delete'
                    }}
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="products.length === 0">
              <td :colspan="isAdmin ? 7 : 6">
                No products found
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <p>Total Products: {{ totalProducts }}</p>

        <div class="pagination-buttons">
          <button
            :disabled="currentPage === 1 || loading"
            @click="changePage(currentPage - 1)"
          >
            Previous
          </button>

          <span>
            Page {{ currentPage }} of {{ lastPage }}
          </span>

          <button
            :disabled="currentPage === lastPage || loading"
            @click="changePage(currentPage + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </section>

    <!-- Purchases -->
    <OrderManagement
      v-if="activeTab === 'purchases'"
      type="purchase"
      @inventory-changed="handleInventoryChanged"
    />

    <!-- Sales -->
    <OrderManagement
      v-if="activeTab === 'sales'"
      type="sale"
      @inventory-changed="handleInventoryChanged"
    />
  </main>
</template>

<style scoped>
.container {
  max-width: 1100px;
  margin: 32px auto;
  padding: 24px;
  font-family: Arial, sans-serif;
  color: #0f172a;
}

.loading-screen {
  display: grid;
  place-items: center;
  min-height: 100vh;
  font-family: Arial, sans-serif;
}

.header,
.account,
.section-header,
.toolbar,
.pagination,
.pagination-buttons,
.row-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header,
.section-header,
.pagination {
  justify-content: space-between;
}

.header {
  margin-bottom: 24px;
}

h1 {
  margin: 0;
}

h2 {
  margin: 0;
  font-size: 20px;
}

.subtitle {
  color: #64748b;
  margin: 6px 0 0;
}

.account-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}

.account-details span {
  color: #64748b;
  text-transform: capitalize;
}

button {
  padding: 10px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  cursor: pointer;
  background: white;
  font: inherit;
}

button:disabled {
  opacity: .5;
  cursor: not-allowed;
}

.erp-nav {
  display: flex;
  gap: 8px;
  padding-bottom: 16px;
  margin-bottom: 24px;
  border-bottom: 1px solid #e2e8f0;
}

.erp-nav .active,
.primary-btn,
.edit-btn {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}

.logout-btn {
  background: #334155;
  color: white;
  border: none;
}

.delete-btn {
  background: #dc2626;
  color: white;
  border: none;
}

.section-header {
  margin-bottom: 20px;
}

.toolbar {
  margin: 20px 0;
}

.toolbar input {
  flex: 1;
  min-width: 0;
  padding: 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
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
  padding: 12px;
  border: 1px solid #e2e8f0;
  text-align: left;
}

th {
  background: #f1f5f9;
}

.pagination {
  margin-top: 20px;
}

.success-banner,
.error-banner {
  padding: 12px;
  border-radius: 6px;
  margin-bottom: 16px;
}

.success-banner {
  background: #dcfce7;
  color: #166534;
}

.error-banner {
  background: #fee2e2;
  color: #991b1b;
}

@media (max-width: 700px) {
  .header,
  .pagination {
    align-items: flex-start;
    flex-direction: column;
  }

  .erp-nav {
    flex-wrap: wrap;
  }

  .account {
    width: 100%;
    justify-content: space-between;
  }
}
</style>

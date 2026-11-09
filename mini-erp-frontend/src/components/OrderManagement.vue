
<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { orderService } from '../services/orderService'

const props = defineProps({
  type: {
    type: String,
    required: true,
    validator: value => ['purchase', 'sale'].includes(value),
  },
})

const emit = defineEmits(['inventory-changed'])

const isPurchase = computed(() => props.type === 'purchase')
const title = computed(() =>
  isPurchase.value ? 'Purchase Orders' : 'Sales Orders'
)

const orders = ref([])
const products = ref([])
const suppliers = ref([])

const loading = ref(false)
const saving = ref(false)
const completingId = ref(null)

const error = ref('')
const success = ref('')
const errors = ref({})

const currentPage = ref(1)
const lastPage = ref(1)

const today = () => new Date().toLocaleDateString('en-CA')

const emptyForm = () => ({
  supplier_id: '',
  customer_name: '',
  order_date: today(),
  items: [
    { product_id: '', quantity: 1, unit_price: '', unit_cost: '' },
  ],
})

const form = reactive(emptyForm())

const resetForm = () => {
  Object.assign(form, emptyForm())
  errors.value = {}
}

const addItem = () => {
  form.items.push({
    product_id: '',
    quantity: 1,
    unit_price: '',
    unit_cost: '',
  })
}

const removeItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1)
  }
}

const formatMoney = value =>
  Number(value || 0).toLocaleString('en-IN', {
    style: 'currency',
    currency: 'INR',
  })

const formTotal = computed(() =>
  form.items.reduce((sum, item) => {
    const price = Number(
      isPurchase.value ? item.unit_cost : item.unit_price
    )
    return sum + (Number(item.quantity) || 0) * (price || 0)
  }, 0)
)

const fetchOrders = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await orderService.getOrders(
      props.type,
      currentPage.value
    )

    orders.value = response.data.data
    currentPage.value = response.data.current_page
    lastPage.value = response.data.last_page
  } catch (err) {
    console.error(err)
    error.value = 'Unable to load orders'
  } finally {
    loading.value = false
  }
}

const fetchOptions = async () => {
  try {
    const [productResponse, supplierResponse] = await Promise.all([
      orderService.getProducts(),
      orderService.getSuppliers(),
    ])

    products.value = productResponse.data.data
    suppliers.value = supplierResponse.data.data
  } catch (err) {
    console.error(err)
    error.value = 'Unable to load products or suppliers'
  }
}

const submitOrder = async () => {
  if (saving.value) return

  saving.value = true
  error.value = ''
  success.value = ''
  errors.value = {}

  const payload = {
    order_date: form.order_date,
    items: form.items.map(item => ({
      product_id: Number(item.product_id),
      quantity: Number(item.quantity),
      [isPurchase.value ? 'unit_cost' : 'unit_price']:
        isPurchase.value ? item.unit_cost : item.unit_price,
    })),
  }

  if (isPurchase.value) {
    payload.supplier_id = Number(form.supplier_id)
  } else {
    payload.customer_name = form.customer_name
  }

  try {
    await orderService.createOrder(props.type, payload)

    success.value = 'Draft order created successfully'
    resetForm()
    currentPage.value = 1

    await fetchOrders()
  } catch (err) {
    console.error(err)

    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
      error.value = 'Please correct the highlighted fields'
    } else {
      error.value = 'Unable to create order'
    }
  } finally {
    saving.value = false
  }
}

const completeOrder = async order => {
  if (completingId.value !== null) return

  const action = isPurchase.value ? 'receive' : 'confirm'

  if (!window.confirm(
    `Are you sure you want to ${action} order ${order.order_number}?`
  )) return

  completingId.value = order.id
  error.value = ''
  success.value = ''

  try {
    await orderService.completeOrder(props.type, order.id)

    success.value = isPurchase.value
      ? 'Purchase received successfully'
      : 'Sales order confirmed successfully'

    await fetchOrders()
    await fetchOptions()

    emit('inventory-changed')
  } catch (err) {
    console.error(err)

    const validationErrors = err.response?.data?.errors

    error.value = validationErrors
      ? Object.values(validationErrors).flat().join(' ')
      : err.response?.data?.message ||
        'Unable to complete order'
  } finally {
    completingId.value = null
  }
}

const changePage = page => {
  if (page < 1 || page > lastPage.value || loading.value) return
  currentPage.value = page
  fetchOrders()
}

watch(() => props.type, () => {
  currentPage.value = 1
  resetForm()
  fetchOrders()
})

onMounted(async () => {
  await Promise.all([fetchOrders(), fetchOptions()])
})
</script>

<template>
  <section class="orders">
    <h2>{{ title }}</h2>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="success" class="success">{{ success }}</p>

    <form class="order-form" @submit.prevent="submitOrder">
      <h3>Create Draft {{ isPurchase ? 'Purchase' : 'Sale' }}</h3>

      <div class="fields">
        <label v-if="isPurchase">
          Supplier
          <select v-model="form.supplier_id">
            <option value="">Select supplier</option>
            <option
              v-for="supplier in suppliers"
              :key="supplier.id"
              :value="supplier.id"
            >
              {{ supplier.name }}
            </option>
          </select>
          <small v-if="errors.supplier_id" class="error">
            {{ errors.supplier_id[0] }}
          </small>
        </label>

        <label v-else>
          Customer Name
          <input v-model.trim="form.customer_name" type="text" />
          <small v-if="errors.customer_name" class="error">
            {{ errors.customer_name[0] }}
          </small>
        </label>

        <label>
          Order Date
          <input v-model="form.order_date" type="date" />
          <small v-if="errors.order_date" class="error">
            {{ errors.order_date[0] }}
          </small>
        </label>
      </div>

      <h4>Order Items</h4>

      <div
        v-for="(item, index) in form.items"
        :key="index"
        class="item-row"
      >
        <label>
          Product
          <select v-model="item.product_id">
            <option value="">Select product</option>
            <option
              v-for="product in products"
              :key="product.id"
              :value="product.id"
            >
              {{ product.name }} ({{ product.sku }})
              - Stock: {{ product.stock_quantity }}
            </option>
          </select>
          <small
            v-if="errors[`items.${index}.product_id`]"
            class="error"
          >
            {{ errors[`items.${index}.product_id`][0] }}
          </small>
        </label>

        <label>
          Quantity
          <input
            v-model.number="item.quantity"
            type="number"
            min="1"
            step="1"
          />
          <small
            v-if="errors[`items.${index}.quantity`]"
            class="error"
          >
            {{ errors[`items.${index}.quantity`][0] }}
          </small>
        </label>

        <label>
          {{ isPurchase ? 'Unit Cost' : 'Unit Price' }}
          <input
            v-if="isPurchase"
            v-model="item.unit_cost"
            type="number"
            min="0"
            step="0.01"
          />
          <input
            v-else
            v-model="item.unit_price"
            type="number"
            min="0"
            step="0.01"
          />
          <small
            v-if="errors[`items.${index}.${isPurchase ? 'unit_cost' : 'unit_price'}`]"
            class="error"
          >
            {{
              errors[
                `items.${index}.${isPurchase ? 'unit_cost' : 'unit_price'}`
              ][0]
            }}
          </small>
        </label>

        <button
          type="button"
          class="remove-btn"
          :disabled="form.items.length === 1"
          @click="removeItem(index)"
        >
          Remove
        </button>
      </div>

      <button type="button" class="secondary-btn" @click="addItem">
        + Add Item
      </button>

      <p class="total">
        Estimated Total: {{ formatMoney(formTotal) }}
      </p>

      <button type="submit" :disabled="saving">
        {{ saving ? 'Saving...' : 'Create Draft Order' }}
      </button>
    </form>

    <h3>Existing Orders</h3>

    <p v-if="loading">Loading orders...</p>

    <table v-else>
      <thead>
        <tr>
          <th>Order Number</th>
          <th>{{ isPurchase ? 'Supplier' : 'Customer' }}</th>
          <th>Total</th>
          <th>Status</th>
          <th>Items</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="order in orders" :key="order.id">
          <td>{{ order.order_number }}</td>
          <td>
            {{
              isPurchase
                ? order.supplier?.name
                : order.customer_name
            }}
          </td>
          <td>{{ formatMoney(order.total_amount) }}</td>
          <td>{{ order.status }}</td>
          <td>
            <div v-for="item in order.items" :key="item.id">
              {{ item.product?.name }} × {{ item.quantity }}
            </div>
          </td>
          <td>
            <button
              v-if="order.status === 'draft'"
              :disabled="completingId !== null"
              @click="completeOrder(order)"
            >
              {{
                completingId === order.id
                  ? 'Processing...'
                  : isPurchase ? 'Receive' : 'Confirm'
              }}
            </button>
            <span v-else>Completed</span>
          </td>
        </tr>

        <tr v-if="orders.length === 0">
          <td colspan="6">No orders found</td>
        </tr>
      </tbody>
    </table>

    <div class="pagination">
      <button
        :disabled="currentPage === 1 || loading"
        @click="changePage(currentPage - 1)"
      >
        Previous
      </button>

      <span>Page {{ currentPage }} of {{ lastPage }}</span>

      <button
        :disabled="currentPage === lastPage || loading"
        @click="changePage(currentPage + 1)"
      >
        Next
      </button>
    </div>
  </section>
</template>

<style scoped>
.orders {
  margin-top: 24px;
}

.order-form {
  padding: 24px;
  margin-bottom: 30px;
  border: 1px solid #ddd;
  border-radius: 10px;
  background: #f9fafb;
}

.fields,
.item-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.item-row {
  grid-template-columns: 2fr 1fr 1fr auto;
  align-items: end;
}

label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-weight: 600;
}

input,
select {
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  min-width: 0;
}

button {
  padding: 10px 14px;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.secondary-btn {
  background: #4b5563;
}

.remove-btn {
  background: #dc2626;
}

.total {
  font-weight: 700;
  margin: 18px 0;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 12px;
  border: 1px solid #ddd;
  text-align: left;
  vertical-align: top;
}

th {
  background: #e5e7eb;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 20px;
}

.error {
  color: #dc2626;
}

.success {
  color: #166534;
  background: #dcfce7;
  padding: 12px;
  border-radius: 6px;
}

small {
  font-weight: 400;
}

@media (max-width: 760px) {
  .fields,
  .item-row {
    grid-template-columns: 1fr;
  }

  table {
    display: block;
    overflow-x: auto;
  }
}
</style>

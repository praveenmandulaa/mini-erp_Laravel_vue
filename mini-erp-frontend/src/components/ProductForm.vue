
<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { productService } from '../services/productService'

const props = defineProps({
  product: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['saved', 'cancel'])

const emptyForm = () => ({
  name: '',
  sku: '',
  description: '',
  price: '',
  cost_price: '',
  reorder_level: 10,
  status: true,
})

const form = reactive(emptyForm())

const errors = ref({})
const saving = ref(false)
const generalError = ref('')

const isEditing = computed(() => Boolean(props.product?.id))

watch(
  () => props.product,
  product => {
    Object.assign(form, emptyForm(), product ?? {})

    errors.value = {}
    generalError.value = ''
  },
  { immediate: true }
)

const submitForm = async () => {
  if (saving.value) return

  saving.value = true
  errors.value = {}
  generalError.value = ''

  // Stock quantity is deliberately excluded.
  const payload = {
    name: form.name,
    sku: form.sku,
    description: form.description || null,
    price: form.price,
    cost_price: form.cost_price,
    reorder_level: form.reorder_level,
    status: form.status,
  }

  try {
    if (isEditing.value) {
      await productService.update(props.product.id, payload)
    } else {
      await productService.create(payload)
    }

    emit('saved')
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}

      generalError.value =
        'Please correct the validation errors below.'
    } else {
      generalError.value =
        error.response?.data?.message ||
        'Unable to save product. Please try again.'

      console.error(error)
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="product-form" @submit.prevent="submitForm">
    <h2>
      {{ isEditing ? 'Edit Product' : 'Add Product' }}
    </h2>

    <p v-if="generalError" class="error">
      {{ generalError }}
    </p>

    <div class="form-grid">
      <div class="field">
        <label for="name">Product Name *</label>

        <input
          id="name"
          v-model.trim="form.name"
          type="text"
          required
        />

        <small v-if="errors.name" class="error">
          {{ errors.name[0] }}
        </small>
      </div>

      <div class="field">
        <label for="sku">SKU *</label>

        <input
          id="sku"
          v-model.trim="form.sku"
          type="text"
          required
        />

        <small v-if="errors.sku" class="error">
          {{ errors.sku[0] }}
        </small>
      </div>

      <div class="field">
        <label for="price">Selling Price *</label>

        <input
          id="price"
          v-model="form.price"
          type="number"
          min="0"
          max="99999999.99"
          step="0.01"
          required
        />

        <small v-if="errors.price" class="error">
          {{ errors.price[0] }}
        </small>
      </div>

      <div class="field">
        <label for="cost_price">Cost Price *</label>

        <input
          id="cost_price"
          v-model="form.cost_price"
          type="number"
          min="0"
          max="99999999.99"
          step="0.01"
          required
        />

        <small v-if="errors.cost_price" class="error">
          {{ errors.cost_price[0] }}
        </small>
      </div>

      <!-- Read-only inventory information -->
      <div class="field stock-field">
        <label>Current Stock</label>

        <div class="stock-display">
          <strong>
            {{ isEditing ? props.product.stock_quantity : 0 }}
          </strong>
          <span>units</span>
        </div>

        <small class="hint">
          {{
            isEditing
              ? 'Stock changes through purchases and sales.'
              : 'New products start with zero stock.'
          }}
        </small>
      </div>

      <div class="field">
        <label for="reorder_level">Reorder Level</label>

        <input
          id="reorder_level"
          v-model.number="form.reorder_level"
          type="number"
          min="0"
          step="1"
        />

        <small v-if="errors.reorder_level" class="error">
          {{ errors.reorder_level[0] }}
        </small>
      </div>

      <div class="field full-width">
        <label for="description">Description</label>

        <textarea
          id="description"
          v-model="form.description"
          rows="3"
        ></textarea>

        <small v-if="errors.description" class="error">
          {{ errors.description[0] }}
        </small>
      </div>

      <div class="field full-width">
        <label class="checkbox-label">
          <input
            v-model="form.status"
            type="checkbox"
          />

          Active Product
        </label>

        <small v-if="errors.status" class="error">
          {{ errors.status[0] }}
        </small>
      </div>
    </div>

    <div class="actions">
      <button
        type="button"
        class="cancel-btn"
        :disabled="saving"
        @click="emit('cancel')"
      >
        Cancel
      </button>

      <button type="submit" :disabled="saving">
        {{
          saving
            ? 'Saving...'
            : isEditing
              ? 'Update Product'
              : 'Create Product'
        }}
      </button>
    </div>
  </form>
</template>

<style scoped>
.product-form {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
}

h2 {
  margin-top: 0;
  margin-bottom: 24px;
  color: #111827;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.full-width {
  grid-column: 1 / -1;
}

label {
  font-weight: 600;
  color: #374151;
}

input:not([type='checkbox']),
textarea {
  width: 100%;
  padding: 11px;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  font: inherit;
}

input:focus,
textarea:focus {
  outline: none;
  border-color: #2563eb;
}

.stock-display {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 10px 12px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 7px;
}

.stock-display strong {
  color: #0f172a;
  font-size: 18px;
}

.stock-display span {
  color: #64748b;
}

.hint {
  color: #64748b;
  font-size: 12px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}

button {
  padding: 11px 18px;
  border: none;
  border-radius: 7px;
  background: #2563eb;
  color: white;
  font-weight: 600;
  cursor: pointer;
}

.cancel-btn {
  background: #6b7280;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error {
  color: #dc2626;
}

@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }

  .product-form {
    padding: 16px;
  }
}
</style>

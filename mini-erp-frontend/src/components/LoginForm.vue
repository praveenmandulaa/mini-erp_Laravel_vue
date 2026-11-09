
<script setup>
import { reactive, ref } from 'vue'
import { authService } from '../services/authService'

const emit = defineEmits(['logged-in'])

const form = reactive({
  email: '',
  password: '',
})

const loading = ref(false)
const error = ref('')

const submit = async () => {
  if (loading.value) return

  loading.value = true
  error.value = ''

  try {
    const response = await authService.login({
      email: form.email.trim(),
      password: form.password,
    })

    form.password = ''
    emit('logged-in', response.user)
  } catch (err) {
    if (err.response?.status === 422) {
      error.value = 'Invalid email or password.'
    } else if (err.response?.status === 419) {
      error.value =
        'Your session expired. Refresh and try again.'
    } else {
      error.value =
        err.response?.data?.message ||
        'Unable to log in. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login-page">
    <form class="login-card" @submit.prevent="submit">
      <div class="brand">Mini ERP</div>

      <h1>Welcome back</h1>

      <p class="subtitle">
        Sign in to manage products, purchases,
        sales and inventory.
      </p>

      <p v-if="error" class="error" role="alert">
        {{ error }}
      </p>

      <label for="email">Email address</label>
      <input
        id="email"
        v-model.trim="form.email"
        type="email"
        autocomplete="username"
        required
      />

      <label for="password">Password</label>
      <input
        id="password"
        v-model="form.password"
        type="password"
        autocomplete="current-password"
        required
      />

      <button type="submit" :disabled="loading">
        {{ loading ? 'Signing in...' : 'Sign In' }}
      </button>

      <p class="footer">
        Mini ERP · Secure employee access
      </p>
    </form>
  </main>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px;
  background: #f1f5f9;
  font-family: Arial, sans-serif;
}

.login-card {
  width: 100%;
  max-width: 420px;
  background: white;
  padding: 36px;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  box-shadow: 0 12px 36px rgba(15, 23, 42, .08);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.brand {
  color: #2563eb;
  font-size: 18px;
  font-weight: 800;
}

h1 {
  margin: 8px 0 0;
  color: #0f172a;
}

.subtitle {
  color: #64748b;
  line-height: 1.5;
  margin: 0 0 12px;
}

label {
  color: #334155;
  font-weight: 600;
  font-size: 14px;
}

input {
  padding: 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font: inherit;
}

input:focus {
  outline: 2px solid #93c5fd;
}

button {
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 13px;
  margin-top: 10px;
  cursor: pointer;
  font-weight: 700;
}

button:disabled {
  opacity: .6;
}

.error {
  padding: 12px;
  color: #991b1b;
  background: #fee2e2;
  border-radius: 8px;
}

.footer {
  text-align: center;
  font-size: 12px;
  color: #94a3b8;
}
</style>

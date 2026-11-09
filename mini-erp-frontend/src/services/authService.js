import api from './api'

export const authService = {
  async login(credentials) {
    // Obtain the CSRF cookie before session login.
    await api.get('/sanctum/csrf-cookie', {
      baseURL: '',
    })

    const response = await api.post(
      '/login',
      credentials,
      { baseURL: '' }
    )

    return response.data
  },

  async me() {
    const response = await api.get('/auth/me')
    return response.data.user
  },

  async logout() {
    const response = await api.post(
      '/logout',
      {},
      { baseURL: '' }
    )

    return response.data
  },
}

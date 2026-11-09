
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],

  server: {
    host: 'localhost',
    port: 5173,

    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },

      '/sanctum': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },

      '/login': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },

      '/logout': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})

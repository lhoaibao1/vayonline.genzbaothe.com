import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// CMS chạy dưới đường dẫn /admin/. Khi dev, /api và /uploads chuyển sang API Go.
const api = process.env.API_URL || 'http://localhost:8080'

export default defineConfig({
  base: '/admin/',
  plugins: [vue()],
  server: { port: 5173, proxy: { '/api': api, '/uploads': api } },
  preview: { port: 4173, proxy: { '/api': api, '/uploads': api } },
})

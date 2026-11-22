import { readFileSync } from 'node:fs'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// DEMO=1: bản demo tĩnh, router dạng #, API giả lập bằng demo/shim.js
const demo = process.env.DEMO === '1'
const demoPlugin = {
  name: 'genz-demo',
  transformIndexHtml: (html: string) => html.replace('<head>', `<head><script>window.__DEMO_APP__='admin';window.__DEMO_FIXTURES__=${readFileSync('../demo/fixtures.json', 'utf8')};\n${readFileSync('../demo/shim.js', 'utf8')}</script>`),
}

// CMS chạy dưới đường dẫn /admin/. Khi dev, /api và /uploads chuyển sang API Go.
const api = process.env.API_URL || 'http://localhost:8080'

export default defineConfig({
  base: demo ? './' : '/admin/',
  define: { __DEMO__: demo },
  plugins: demo ? [vue(), demoPlugin] : [vue()],
  server: { port: 5173, proxy: { '/api': api, '/uploads': api } },
  preview: { port: 4173, proxy: { '/api': api, '/uploads': api } },
})

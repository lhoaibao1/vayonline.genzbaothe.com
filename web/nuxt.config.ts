// Landing GenZbaothe.com: Nuxt 3, render ở server để Google đọc được và mở nhanh trên điện thoại
export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: false },
  css: ['~/assets/css/site.css'],
  app: {
    head: {
      htmlAttrs: { lang: 'vi' },
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'theme-color', content: '#6c2bd9' },
      ],
      link: [
        { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap' },
      ],
    },
  },
  runtimeConfig: {
    // Địa chỉ nội bộ để server Nuxt gọi API Go và tracker Rust (đặt bằng NUXT_API_INTERNAL, NUXT_TRACKER_INTERNAL)
    apiInternal: 'http://localhost:8080',
    trackerInternal: 'http://localhost:8081',
    // Bật khi chạy Nuxt không có Caddy phía trước: Nuxt tự chuyển /api, /uploads, /go, /t sang đúng service
    proxyBackends: false,
  },
})

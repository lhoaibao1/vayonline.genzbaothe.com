// Chỉ dùng khi chạy Nuxt một mình (NUXT_PROXY_BACKENDS=true).
// Trên production Caddy chuyển các đường dẫn này thẳng tới API Go và tracker Rust.
export default defineEventHandler((event) => {
  const cfg = useRuntimeConfig(event)
  if (!cfg.proxyBackends) return
  const path = event.path
  if (path.startsWith('/api/') || path.startsWith('/uploads/')) {
    return proxyRequest(event, cfg.apiInternal + path)
  }
  if (path.startsWith('/go/') || path.startsWith('/t/')) {
    return proxyRequest(event, cfg.trackerInternal + path, { fetchOptions: { redirect: 'manual' } })
  }
})

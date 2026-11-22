// Mã theo dõi (GA, GTM, Pixel…) nhập trong CMS KHÔNG được chèn thẳng vào trang.
// Server chỉ gửi kèm dưới dạng dữ liệu; banner cookie chạy chúng sau khi khách đồng ý.
let cache: { at: number; head: string; body: string } | null = null

async function scripts(apiBase: string) {
  if (cache && Date.now() - cache.at < 60_000) return cache
  try {
    const s = await $fetch<{ head_scripts: string; body_scripts: string }>(apiBase + '/api/public/site')
    cache = { at: Date.now(), head: s.head_scripts || '', body: s.body_scripts || '' }
  } catch {
    cache = { at: Date.now() - 50_000, head: '', body: '' }
  }
  return cache
}

export default defineNitroPlugin((nitro) => {
  nitro.hooks.hook('render:html', async (html, { event }) => {
    const s = await scripts(useRuntimeConfig(event).apiInternal)
    if (!s.head && !s.body) return
    // Escape "<" để chuỗi không thể đóng thẻ <script> sớm
    const data = JSON.stringify({ head: s.head, body: s.body }).replace(/</g, '\\u003c')
    html.head.push(`<script>window.__GZ_TRACKING__=${data}</script>`)
  })
})

// Chèn mã theo dõi (GA, GTM, Pixel…) nhập trong CMS vào <head> và cuối <body>.
// Lấy từ API, giữ trong bộ nhớ 60 giây.
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
    if (s.head) html.head.push(s.head)
    if (s.body) html.bodyAppend.push(s.body)
  })
})

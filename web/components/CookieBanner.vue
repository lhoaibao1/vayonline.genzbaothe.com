<script setup lang="ts">
// Banner đồng ý cookie. Cookie cần thiết luôn bật; mã đo lường & quảng cáo chỉ chạy khi khách đồng ý.
// Lựa chọn lưu trong cookie gz_consent 180 ngày. Đổi CONSENT_VERSION khi thay đổi loại cookie để hỏi lại.
const CONSENT_VERSION = 1
const COOKIE = 'gz_consent'

const open = useState('cookie-banner-open', () => false)
const detail = ref(false)
const marketing = ref(false)
let loaded = false

function read(): { v: number; marketing: boolean } | null {
  const m = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'))
  if (!m) return null
  try {
    const c = JSON.parse(decodeURIComponent(m[1]))
    return c.v === CONSENT_VERSION ? c : null
  } catch {
    return null
  }
}

function save(allowMarketing: boolean) {
  const val = encodeURIComponent(JSON.stringify({ v: CONSENT_VERSION, marketing: allowMarketing, at: new Date().toISOString() }))
  document.cookie = `${COOKIE}=${val}; path=/; max-age=${180 * 86400}; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`
  open.value = false
  detail.value = false
  if (allowMarketing) loadTracking()
  else if (loaded) location.reload() // đã chạy mã theo dõi mà khách rút lại: tải lại trang để dừng
}

// Chạy mã theo dõi đã nhập trong CMS (thẻ <script> chèn bằng innerHTML không tự chạy nên phải tạo lại)
function inject(html: string, target: HTMLElement) {
  const tpl = document.createElement('template')
  tpl.innerHTML = html
  for (const node of Array.from(tpl.content.childNodes)) {
    if (node instanceof HTMLScriptElement) {
      const s = document.createElement('script')
      for (const a of Array.from(node.attributes)) s.setAttribute(a.name, a.value)
      s.text = node.text
      target.appendChild(s)
    } else {
      target.appendChild(node.cloneNode(true))
    }
  }
}
function loadTracking() {
  if (loaded) return
  loaded = true
  const t = (window as any).__GZ_TRACKING__ as { head: string; body: string } | undefined
  if (!t) return
  if (t.head) inject(t.head, document.head)
  if (t.body) inject(t.body, document.body)
}

onMounted(() => {
  const c = read()
  if (!c) open.value = true
  else if (c.marketing) loadTracking()
  marketing.value = !!c?.marketing
})
watch(open, (v) => { if (v) { const c = read(); marketing.value = !!c?.marketing } })
</script>

<template>
  <Transition name="cookie">
    <section v-if="open" class="cookie" role="dialog" aria-modal="false" aria-labelledby="cookie-title">
      <div class="cookie__in">
        <div class="cookie__text">
          <h2 id="cookie-title">🍪 Website dùng cookie</h2>
          <p>
            Cookie cần thiết giúp website chạy và ghi nhận nguồn đăng ký. Với sự đồng ý của bạn, chúng tôi dùng thêm cookie đo lường và quảng cáo
            (Google Analytics, Facebook, TikTok) để biết quảng cáo nào hiệu quả. Xem <NuxtLink to="/chinh-sach-bao-mat">Chính sách bảo mật</NuxtLink>.
          </p>
        </div>

        <div v-if="detail" class="cookie__detail">
          <label class="cookie__opt">
            <input type="checkbox" checked disabled>
            <span><b>Cần thiết</b> · luôn bật. Ghi nhớ lựa chọn cookie, nguồn quảng cáo đưa bạn đến, chống spam form.</span>
          </label>
          <label class="cookie__opt">
            <input v-model="marketing" type="checkbox">
            <span><b>Đo lường &amp; quảng cáo</b> · Google Analytics, Google Tag Manager, Facebook Pixel, TikTok Pixel và mã tương tự.</span>
          </label>
        </div>

        <div class="cookie__btns">
          <template v-if="!detail">
            <button type="button" class="btn btn--gold" @click="save(true)">Chấp nhận tất cả</button>
            <button type="button" class="btn btn--ghost" @click="save(false)">Chỉ cần thiết</button>
            <button type="button" class="cookie__link" @click="detail = true">Tuỳ chỉnh</button>
          </template>
          <template v-else>
            <button type="button" class="btn btn--gold" @click="save(marketing)">Lưu lựa chọn</button>
            <button type="button" class="btn btn--ghost" @click="save(true)">Chấp nhận tất cả</button>
          </template>
        </div>
      </div>
    </section>
  </Transition>
</template>

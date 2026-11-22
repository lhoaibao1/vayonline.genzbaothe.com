<script setup lang="ts">
import type { LandingData } from '~/composables/api'

const props = defineProps<{ data: LandingData }>()
const { data: site } = await useSite()
const reg = useRegister()

const L = computed(() => props.data.landing)
const loans = computed(() => props.data.loans)
const cards = computed(() => props.data.cards)
const total = computed(() => loans.value.length + cards.value.length)

useSeoMeta({
  title: () => L.value.seo_title || plainHeadline(L.value.headline),
  description: () => L.value.seo_description || site.value?.site_tagline,
  ogTitle: () => L.value.seo_title || plainHeadline(L.value.headline),
  ogDescription: () => L.value.seo_description || site.value?.site_tagline,
})

/* ---------- Công cụ tính khoản vay ---------- */
const maxAmount = computed(() => Math.max(0, ...loans.value.map((o) => o.amount_max)))
const minAmount = computed(() => Math.max(1e6, Math.min(...loans.value.map((o) => o.amount_min || 1e6))))
const showCalc = computed(() => L.value.show_calculator && loans.value.length > 0 && maxAmount.value > 0)
const amount = ref(Math.min(maxAmount.value || 30e6, Math.max(minAmount.value, 30e6)))
const term = ref(12)
const pct = computed(() => ((amount.value - minAmount.value) / Math.max(1, maxAmount.value - minAmount.value)) * 100)
watchEffect(() => {
  reg.value.amount = amount.value
  reg.value.term = term.value
  reg.value.landingId = L.value.id
})

const loanRows = computed(() => {
  const rows = loans.value.map((o) => {
    const fitAmount = !o.amount_max || (amount.value >= o.amount_min && amount.value <= o.amount_max)
    const fitTerm = !o.term_max || (term.value >= o.term_min && term.value <= o.term_max)
    const fit = fitAmount && fitTerm
    let text = ''
    if (showCalc.value) {
      text = fit
        ? o.rate_min > 0
          ? `Vay ${short(amount.value)} trong ${term.value} tháng, trả khoảng ${fmt(monthly(amount.value, o.rate_min, term.value))}đ/tháng`
          : 'Phù hợp với số tiền và kỳ hạn bạn chọn'
        : !fitAmount
          ? `Hạn mức gói này: ${short(o.amount_min)} – ${short(o.amount_max)}`
          : `Kỳ hạn gói này: ${o.term_min} – ${o.term_max} tháng`
    }
    return { offer: o, fit: showCalc.value ? (fit ? 'fit' : 'out') as const : null, text }
  })
  return showCalc.value ? [...rows].sort((a, b) => Number(a.fit === 'out') - Number(b.fit === 'out')) : rows
})
const fitCount = computed(() => loanRows.value.filter((r) => r.fit === 'fit').length)
const bestRate = computed(() => {
  const rates = loanRows.value.filter((r) => r.fit === 'fit' && r.offer.rate_min > 0).map((r) => r.offer.rate_min)
  return rates.length ? Math.min(...rates) : null
})

/* ---------- Ghi lượt xem (Rust tracker) + lưu nguồn quảng cáo ---------- */
onMounted(() => {
  const q = useRoute().query
  try {
    for (const k of ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content']) {
      if (typeof q[k] === 'string' && q[k]) sessionStorage.setItem(k, String(q[k]).slice(0, 120))
    }
    if (!sessionStorage.getItem('referrer') && document.referrer && !document.referrer.startsWith(location.origin)) {
      sessionStorage.setItem('referrer', document.referrer.slice(0, 255))
    }
    const key = `viewed_${L.value.id}_${new Date().toISOString().slice(0, 10)}`
    if (!sessionStorage.getItem(key)) {
      sessionStorage.setItem(key, '1')
      navigator.sendBeacon?.(`/t/view/${L.value.id}`)
    }
  } catch { /* trình duyệt chặn sessionStorage: bỏ qua */ }
})
</script>

<template>
  <div>
    <section class="hero">
      <div class="wrap hero__in" :class="{ 'hero__in--solo': !showCalc }">
        <div class="hero__copy">
          <p class="hero__kicker"><b aria-hidden="true" />{{ total }} gói đang nhận hồ sơ hôm nay</p>
          <h1 v-html="headlineHtml(L.headline)" />
          <p v-if="L.subheadline" class="hero__sub">{{ L.subheadline }}</p>
          <div class="chips">
            <a v-if="loans.length" class="chip chip--violet" href="#khoan-vay"><span aria-hidden="true">💸</span>Khoản vay</a>
            <a v-if="cards.length" class="chip chip--pink" href="#the-tin-dung"><span aria-hidden="true">💳</span>Thẻ tín dụng</a>
            <a class="chip chip--mint" href="#cach-dang-ky"><span aria-hidden="true">⚡</span>Duyệt từ 15 phút</a>
          </div>
          <a v-if="!showCalc" class="btn btn--gold btn--lg" :href="loans.length ? '#khoan-vay' : '#the-tin-dung'">Xem các gói</a>
        </div>

        <form v-if="showCalc" class="calc" aria-label="Tính khoản vay" @submit.prevent>
          <span class="sticker sticker--a" aria-hidden="true">0đ phí đăng ký</span>
          <span class="sticker sticker--b" aria-hidden="true">Chỉ cần CCCD 🪪</span>
          <label class="calc__label" for="calc-amount">Bạn cần bao nhiêu?</label>
          <output class="calc__amount" for="calc-amount">{{ fmt(amount) }}<span>đ</span></output>
          <input id="calc-amount" v-model.number="amount" class="calc__range" type="range" :min="minAmount" :max="maxAmount" step="1000000" :style="{ '--p': pct + '%' }">
          <div class="calc__scale"><span>{{ short(minAmount) }}</span><span>{{ short(maxAmount) }}</span></div>
          <fieldset class="calc__terms">
            <legend>Trả trong</legend>
            <label v-for="t in [6, 12, 24, 36]" :key="t"><input v-model.number="term" type="radio" name="term" :value="t"><span>{{ t }} tháng</span></label>
          </fieldset>
          <div class="calc__result" aria-live="polite">
            <p>Trả mỗi tháng từ khoảng</p>
            <p class="calc__monthly">{{ bestRate !== null ? fmt(monthly(amount, bestRate, term)) + 'đ' : '—' }}</p>
            <p class="calc__hint">
              {{ fitCount ? `Ước tính theo lãi suất thấp nhất trong ${fitCount} gói phù hợp. Số thực tế do đối tác báo.` : 'Chưa có gói khớp số tiền và kỳ hạn này. Thử giảm số tiền hoặc đổi kỳ hạn.' }}
            </p>
          </div>
          <a class="btn btn--gold btn--block btn--lg" href="#khoan-vay">{{ fitCount ? `Xem ${fitCount} gói phù hợp` : 'Xem tất cả gói vay' }}</a>
        </form>
      </div>
    </section>

    <section v-if="!total" class="offers"><div class="wrap"><p class="empty">Hiện chưa có gói nào nhận đăng ký. Hãy quay lại sau.</p></div></section>

    <section v-if="loans.length" id="khoan-vay" class="offers offers--loan">
      <div class="wrap">
        <div class="offers__head">
          <div>
            <p class="module-tag module-tag--loan">💸 Khoản vay</p>
            <h2 class="sec-title">Vay tiêu dùng online</h2>
            <p class="sec-sub">Giải ngân vào tài khoản ngân hàng, trả góp cố định hằng tháng.</p>
          </div>
          <NuxtLink v-if="L.offer_type === 'all'" class="offers__link" to="/khoan-vay">Trang riêng khoản vay</NuxtLink>
        </div>
        <TransitionGroup tag="div" name="reorder" class="offer-list">
          <OfferCard v-for="r in loanRows" :key="r.offer.id" :offer="r.offer" :fit="r.fit" :fit-text="r.text" />
        </TransitionGroup>
      </div>
    </section>

    <section v-if="cards.length" id="the-tin-dung" class="offers offers--card">
      <div class="wrap">
        <div class="offers__head">
          <div>
            <p class="module-tag module-tag--card">💳 Thẻ tín dụng</p>
            <h2 class="sec-title">Mở thẻ tín dụng online</h2>
            <p class="sec-sub">Chi tiêu trước, trả sau. Miễn lãi trong kỳ, hoàn tiền và trả góp 0%.</p>
          </div>
          <NuxtLink v-if="L.offer_type === 'all'" class="offers__link" to="/the-tin-dung">Trang riêng thẻ tín dụng</NuxtLink>
        </div>
        <div class="offer-list">
          <OfferCard v-for="o in cards" :key="o.id" :offer="o" />
        </div>
      </div>
    </section>

    <section id="cach-dang-ky" class="steps">
      <div class="wrap">
        <h2 class="sec-title">3 bước là xong</h2>
        <ol class="steps__list">
          <li><h3>Chọn gói</h3><p>So sánh hạn mức, lãi suất và thời gian duyệt. Bấm đăng ký ở gói bạn thấy hợp.</p></li>
          <li><h3>Để lại thông tin</h3><p>Điền vài thông tin cơ bản trong 2 phút. Bạn được chuyển sang trang đối tác để hoàn tất hồ sơ.</p></li>
          <li><h3>Nhận kết quả</h3><p>Đối tác thẩm định và báo kết quả qua điện thoại. Tiền vay hoặc thẻ về thẳng tay bạn.</p></li>
        </ol>
        <p class="steps__warn">Không đối tác nào yêu cầu bạn chuyển tiền trước để “mở khoá” hay “chứng minh tài chính”. Gặp yêu cầu như vậy, hãy dừng lại và báo cho chúng tôi.</p>
      </div>
    </section>

    <section v-if="site?.faq?.length" id="hoi-dap" class="faq">
      <div class="wrap faq__in">
        <h2 class="sec-title">Hỏi nhanh, đáp gọn</h2>
        <div class="faq__list">
          <details v-for="(f, i) in site.faq" :key="i"><summary>{{ f.q }}</summary><p>{{ f.a }}</p></details>
        </div>
      </div>
    </section>
  </div>
</template>

<style>
.reorder-move { transition: transform .35s ease; }
@media (prefers-reduced-motion: reduce) { .reorder-move { transition: none; } }
</style>

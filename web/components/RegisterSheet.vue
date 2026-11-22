<script setup lang="ts">
// Form đăng ký: phần câu hỏi khác nhau cho khoản vay và thẻ tín dụng
const reg = useRegister()
const { data: opts } = await useOptions()
const { data: site } = await useSite()
const siteName = computed(() => site.value?.site_name || 'website')
const dlg = ref<HTMLDialogElement | null>(null)
const year = new Date().getFullYear()
const years = Array.from({ length: 53 }, (_, i) => year - 18 - i)

const blank = () => ({
  full_name: '', phone: '', email: '', birth_year: '' as number | '', province: '', occupation: '', income: '',
  amount: '', term: '' as number | '', purpose: '', salary_method: '', has_card: '', card_need: '', consent: false, website: '',
})
const f = reactive(blank())
const errors = ref<Record<string, string>>({})
const message = ref('')
const sending = ref(false)
const done = ref<null | { redirect: string; offer: string; type: string }>(null)
const countdown = ref(0)
let timer: ReturnType<typeof setInterval> | undefined

const offer = computed(() => reg.value.offer)
const isLoan = computed(() => offer.value?.type !== 'card')
const who = computed(() => offer.value?.partner || offer.value?.name || '')

watch(offer, (o) => {
  if (!o) return
  errors.value = {}
  message.value = ''
  done.value = null
  clearInterval(timer)
  if (o.type === 'loan') {
    let a = reg.value.amount || 0
    if (a && o.amount_max) a = Math.min(Math.max(a, o.amount_min), o.amount_max)
    f.amount = a ? fmt(a) : ''
    f.term = opts.value?.terms.includes(reg.value.term) ? reg.value.term : ''
  }
  nextTick(() => {
    dlg.value?.showModal()
    ;(dlg.value?.querySelector('input[name=full_name]') as HTMLInputElement | null)?.focus()
  })
})

function close() {
  clearInterval(timer)
  dlg.value?.close()
}
function onClose() {
  reg.value.offer = null
}
function onAmount(e: Event) {
  const d = (e.target as HTMLInputElement).value.replace(/\D/g, '')
  f.amount = d ? fmt(+d) : ''
}

function check() {
  const e: Record<string, string> = {}
  const phone = f.phone.replace(/\D/g, '').replace(/^84/, '0')
  if (f.full_name.trim().length < 2) e.full_name = 'Nhập họ tên như trên CCCD.'
  if (!/^0[35789]\d{8}$/.test(phone)) e.phone = 'Số điện thoại cần đủ 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09.'
  if (!f.birth_year) e.birth_year = 'Chọn năm sinh.'
  if (!f.province) e.province = 'Chọn tỉnh/thành bạn đang sống.'
  if (!f.occupation) e.occupation = 'Chọn công việc hiện tại.'
  if (!f.income) e.income = 'Chọn mức thu nhập hằng tháng.'
  if (isLoan.value) {
    if (!f.amount) e.amount = 'Nhập số tiền muốn vay.'
    if (!f.term) e.term = 'Chọn thời gian trả.'
    if (!f.purpose) e.purpose = 'Chọn mục đích vay.'
    if (!f.salary_method) e.salary_method = 'Chọn hình thức nhận lương.'
  } else {
    if (f.email && !/^\S+@\S+\.\S+$/.test(f.email)) e.email = 'Email chưa đúng định dạng, ví dụ: ten@gmail.com.'
    if (!f.has_card) e.has_card = 'Cho biết bạn đã có thẻ tín dụng chưa.'
    if (!f.card_need) e.card_need = 'Chọn nhu cầu dùng thẻ.'
  }
  if (!f.consent) e.consent = 'Bạn cần đồng ý với điều khoản và chính sách dữ liệu cá nhân để gửi đăng ký.'
  return e
}

async function submit() {
  message.value = ''
  errors.value = check()
  if (Object.keys(errors.value).length) {
    nextTick(() => dlg.value?.querySelector('.has-err')?.scrollIntoView({ block: 'center', behavior: 'smooth' }))
    return
  }
  const ss = (k: string) => { try { return sessionStorage.getItem(k) || '' } catch { return '' } }
  sending.value = true
  try {
    const res = await $fetch<{ ok: boolean; redirect: string; offer: string; type: string }>('/api/public/register', {
      method: 'POST',
      body: {
        offer_id: offer.value!.id, landing_id: reg.value.landingId,
        full_name: f.full_name, phone: f.phone, email: isLoan.value ? '' : f.email, birth_year: Number(f.birth_year),
        province: f.province, occupation: f.occupation, income: f.income,
        amount: isLoan.value ? Number(String(f.amount).replace(/\D/g, '')) : 0, term: isLoan.value ? Number(f.term) : 0,
        purpose: f.purpose, salary_method: f.salary_method, has_card: f.has_card, card_need: f.card_need,
        consent: f.consent, website: f.website,
        utm_source: ss('utm_source'), utm_medium: ss('utm_medium'), utm_campaign: ss('utm_campaign'), utm_content: ss('utm_content'), referrer: ss('referrer'),
      },
    })
    const w = window as any
    w.dataLayer?.push({ event: 'lead_submit', offer: res.offer, offer_type: res.type })
    w.fbq?.('track', 'Lead')
    done.value = res
    f.consent = false
    if (res.redirect) {
      countdown.value = 5
      timer = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) {
          clearInterval(timer)
          location.href = res.redirect
        }
      }, 1000)
    }
  } catch (err: any) {
    const d = err?.data
    if (d?.errors) errors.value = d.errors
    message.value = d?.message || 'Không gửi được do mất kết nối. Kiểm tra mạng rồi bấm gửi lại.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <dialog ref="dlg" class="sheet" aria-labelledby="dk-title" @close="onClose" @click.self="close">
    <div class="sheet__body">
      <button type="button" class="sheet__close" aria-label="Đóng" @click="close">×</button>

      <form v-if="offer && !done" class="regform" novalidate @submit.prevent="submit">
        <div class="regform__offer">
          <img v-if="offer.logo" class="logo logo--sm" :src="offer.logo" alt="">
          <span v-else class="logo logo--sm">{{ initials(who) }}</span>
          <div>
            <p class="regform__eyebrow">{{ isLoan ? 'Đăng ký khoản vay' : 'Mở thẻ tín dụng' }}{{ offer.partner ? ' · ' + offer.partner : '' }}</p>
            <h2 id="dk-title">{{ offer.name }}</h2>
          </div>
        </div>
        <div class="hp" aria-hidden="true"><label>Website <input v-model="f.website" tabindex="-1" autocomplete="off"></label></div>

        <p class="regform__group">Thông tin của bạn</p>
        <label class="field" :class="{ 'has-err': errors.full_name }">
          <span>Họ và tên</span>
          <input v-model="f.full_name" name="full_name" autocomplete="name" maxlength="80" placeholder="Như trên CCCD">
          <small class="field__err">{{ errors.full_name }}</small>
        </label>
        <div class="field-row">
          <label class="field" :class="{ 'has-err': errors.phone }">
            <span>Số điện thoại</span>
            <input v-model="f.phone" type="tel" inputmode="numeric" autocomplete="tel" maxlength="12" placeholder="09xx xxx xxx">
            <small class="field__err">{{ errors.phone }}</small>
          </label>
          <label class="field" :class="{ 'has-err': errors.birth_year }">
            <span>Năm sinh</span>
            <select v-model.number="f.birth_year"><option value="">Chọn năm</option><option v-for="y in years" :key="y" :value="y">{{ y }}</option></select>
            <small class="field__err">{{ errors.birth_year }}</small>
          </label>
        </div>
        <label v-if="!isLoan" class="field" :class="{ 'has-err': errors.email }">
          <span>Email <em>(nhận thông báo mở thẻ)</em></span>
          <input v-model="f.email" type="email" autocomplete="email" placeholder="ten@gmail.com">
          <small class="field__err">{{ errors.email }}</small>
        </label>
        <label class="field" :class="{ 'has-err': errors.province }">
          <span>Tỉnh / thành phố đang sống</span>
          <select v-model="f.province"><option value="">Chọn nơi ở</option><option v-for="p in opts?.provinces" :key="p">{{ p }}</option></select>
          <small class="field__err">{{ errors.province }}</small>
        </label>
        <div class="field-row">
          <label class="field" :class="{ 'has-err': errors.occupation }">
            <span>Công việc hiện tại</span>
            <select v-model="f.occupation"><option value="">Chọn công việc</option><option v-for="o in opts?.occupation" :key="o.value" :value="o.value">{{ o.label }}</option></select>
            <small class="field__err">{{ errors.occupation }}</small>
          </label>
          <label class="field" :class="{ 'has-err': errors.income }">
            <span>Thu nhập mỗi tháng</span>
            <select v-model="f.income"><option value="">Chọn mức</option><option v-for="o in opts?.income" :key="o.value" :value="o.value">{{ o.label }}</option></select>
            <small class="field__err">{{ errors.income }}</small>
          </label>
        </div>

        <fieldset v-if="isLoan" class="regform__part">
          <legend class="regform__group">Khoản vay bạn cần</legend>
          <div class="field-row">
            <label class="field" :class="{ 'has-err': errors.amount }">
              <span>Số tiền muốn vay</span>
              <input :value="f.amount" inputmode="numeric" placeholder="30.000.000" @input="onAmount">
              <small class="field__err">{{ errors.amount }}</small>
            </label>
            <label class="field" :class="{ 'has-err': errors.term }">
              <span>Thời gian trả</span>
              <select v-model.number="f.term"><option value="">Chọn kỳ hạn</option><option v-for="t in opts?.terms" :key="t" :value="t">{{ t }} tháng</option></select>
              <small class="field__err">{{ errors.term }}</small>
            </label>
          </div>
          <div class="field-row">
            <label class="field" :class="{ 'has-err': errors.purpose }">
              <span>Mục đích vay</span>
              <select v-model="f.purpose"><option value="">Chọn mục đích</option><option v-for="o in opts?.purpose" :key="o.value" :value="o.value">{{ o.label }}</option></select>
              <small class="field__err">{{ errors.purpose }}</small>
            </label>
            <label class="field" :class="{ 'has-err': errors.salary_method }">
              <span>Nhận lương qua</span>
              <select v-model="f.salary_method"><option value="">Chọn hình thức</option><option v-for="o in opts?.salary_method" :key="o.value" :value="o.value">{{ o.label }}</option></select>
              <small class="field__err">{{ errors.salary_method }}</small>
            </label>
          </div>
        </fieldset>

        <fieldset v-else class="regform__part">
          <legend class="regform__group">Nhu cầu mở thẻ</legend>
          <div class="field-row">
            <label class="field" :class="{ 'has-err': errors.has_card }">
              <span>Bạn đã có thẻ tín dụng?</span>
              <select v-model="f.has_card"><option value="">Chọn</option><option v-for="o in opts?.has_card" :key="o.value" :value="o.value">{{ o.label }}</option></select>
              <small class="field__err">{{ errors.has_card }}</small>
            </label>
            <label class="field" :class="{ 'has-err': errors.card_need }">
              <span>Dùng thẻ chủ yếu để</span>
              <select v-model="f.card_need"><option value="">Chọn nhu cầu</option><option v-for="o in opts?.card_need" :key="o.value" :value="o.value">{{ o.label }}</option></select>
              <small class="field__err">{{ errors.card_need }}</small>
            </label>
          </div>
        </fieldset>

        <label class="check" :class="{ 'has-err': errors.consent }">
          <input v-model="f.consent" type="checkbox">
          <span>
            Tôi đã đọc và đồng ý với <a href="/dieu-khoan-su-dung" target="_blank" rel="noopener">Điều khoản sử dụng</a>,
            <a href="/chinh-sach-bao-mat" target="_blank" rel="noopener">Chính sách bảo mật</a> và
            <a href="/bao-ve-du-lieu-ca-nhan" target="_blank" rel="noopener">Thông báo xử lý dữ liệu cá nhân</a>;
            đồng ý cho {{ siteName }} xử lý thông tin trên (gồm thông tin thu nhập{{ isLoan ? ', nhu cầu vay' : ', nhu cầu mở thẻ' }})
            và chuyển cho {{ offer.partner || 'đối tác' }} để liên hệ tư vấn, thẩm định hồ sơ.
          </span>
        </label>
        <small class="field__err">{{ errors.consent }}</small>
        <p class="form-msg" role="alert">{{ message }}</p>

        <button class="btn btn--gold btn--block" type="submit" :disabled="sending">
          {{ sending ? 'Đang gửi…' : isLoan ? 'Gửi đăng ký vay' : 'Đăng ký mở thẻ' }}
        </button>
        <p class="regform__note">Miễn phí. Không yêu cầu chuyển tiền trước dưới bất kỳ hình thức nào.</p>
      </form>

      <div v-else-if="done" class="done">
        <div class="done__tick" aria-hidden="true" />
        <h2>Đã nhận đăng ký</h2>
        <p v-if="done.redirect">Bước tiếp theo: hoàn tất hồ sơ “{{ done.offer }}” tại trang đối tác để được duyệt nhanh hơn.</p>
        <p v-else>Tư vấn viên sẽ gọi cho bạn để hướng dẫn hồ sơ “{{ done.offer }}”.</p>
        <a v-if="done.redirect" class="btn btn--gold btn--block" :href="done.redirect">Tiếp tục hồ sơ tại trang đối tác</a>
        <p v-if="done.redirect && countdown > 0" class="done__count">Tự động chuyển sau {{ countdown }} giây</p>
        <button type="button" class="btn btn--ghost btn--block" @click="close">Xem thêm gói khác</button>
      </div>
    </div>
  </dialog>
</template>

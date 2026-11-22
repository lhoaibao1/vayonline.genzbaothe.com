<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, notify, ui } from '../api'
import { fmt, modText, moneyInput, typeMod, type Mod } from '../util'

const route = useRoute()
const router = useRouter()
const id = route.params.id ? Number(route.params.id) : 0
const newType = route.params.mod === 'cards' ? 'card' : 'loan'

const o = reactive<any>({
  type: newType, name: '', slug: '', partner: '', logo: '', badge: '',
  amount_min: newType === 'card' ? 10_000_000 : 1_000_000, amount_max: newType === 'card' ? 100_000_000 : 50_000_000,
  rate_text: '', rate_min: 0, term_min: newType === 'card' ? 0 : 3, term_max: newType === 'card' ? 0 : 36,
  age_text: '', approval_text: '', annual_fee: '', highlights: '', requirements: '', description: '', affiliate_url: '', sort_order: 100, active: true,
})
const loaded = ref(!id)
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const uploading = ref(false)
const confirmDel = ref(false)

const mod = computed<Mod>(() => typeMod(o.type))
const T = computed(() => modText[mod.value])
const isLoan = computed(() => o.type === 'loan')
const title = computed(() => (id ? `Sửa ${T.value.one}` : `Thêm ${T.value.one}`))

if (id) {
  api(`/offers/${id}`).then((d) => {
    Object.assign(o, d)
    loaded.value = true
    ui.navKey = `/${mod.value}/offers`
    document.title = `${d.name} – GenZbaothe.com`
  })
} else {
  ui.navKey = `/${mod.value}/offers`
}

const money = (k: 'amount_min' | 'amount_max') => computed({
  get: () => (o[k] ? fmt(o[k]) : ''),
  set: (v: string) => (o[k] = moneyInput(v)),
})
const amountMin = money('amount_min')
const amountMax = money('amount_max')

async function upload(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const fd = new FormData()
  fd.append('file', file)
  uploading.value = true
  try {
    o.logo = (await api('/uploads', { method: 'POST', body: fd })).url
    errors.value.logo = ''
  } catch (err: any) {
    errors.value = { ...errors.value, logo: err.message }
  } finally {
    uploading.value = false
  }
}

async function save() {
  errors.value = {}
  saving.value = true
  try {
    await api(id ? `/offers/${id}` : '/offers', { method: id ? 'PUT' : 'POST', body: { ...o, rate_min: Number(String(o.rate_min).replace(',', '.')) || 0, term_min: Number(o.term_min), term_max: Number(o.term_max), sort_order: Number(o.sort_order) } })
    notify(`Đã lưu “${o.name}”.`)
    router.push(`/${mod.value}/offers`)
  } catch (e: any) {
    errors.value = e.errors
    notify(e.message, 'err')
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!confirmDel.value) return (confirmDel.value = true)
  await api(`/offers/${id}`, { method: 'DELETE' })
  notify('Đã xoá gói. Đăng ký cũ vẫn giữ tên gói tại thời điểm đăng ký.')
  router.push(`/${mod.value}/offers`)
}
</script>

<template>
  <div>
    <header class="mtop">
      <h1>{{ title }}</h1>
      <RouterLink class="b b--ghost" :to="`/${mod}/offers`">Danh sách {{ T.offers.toLowerCase() }}</RouterLink>
    </header>

    <form v-if="loaded" class="form-grid" @submit.prevent="save">
      <div class="stack">
        <section class="card stack">
          <h2 class="card__t">Thông tin chính</h2>
          <div class="row2">
            <div class="f"><span>Module</span><p class="fixed-val"><span class="chip" :class="`chip--${o.type}`">{{ T.name }}</span></p></div>
            <label class="f"><span>Nhãn nổi bật (tuỳ chọn)</span><input v-model="o.badge" maxlength="24" placeholder="Ví dụ: Duyệt nhanh"></label>
          </div>
          <label class="f"><span>Tên gói</span><input v-model="o.name" required :placeholder="isLoan ? 'Ví dụ: Vay tiêu dùng chỉ cần CCCD' : 'Ví dụ: Thẻ hoàn tiền mua sắm'"><small v-if="errors.name" class="err">{{ errors.name }}</small></label>
          <div class="row2">
            <label class="f"><span>Đối tác / ngân hàng</span><input v-model="o.partner"></label>
            <label class="f"><span>Đường dẫn</span><input v-model="o.slug" placeholder="tự tạo từ tên gói"><small v-if="errors.slug" class="err">{{ errors.slug }}</small><small class="hint">/uu-dai/{{ o.slug || '…' }}</small></label>
          </div>
          <label class="f"><span>Mô tả ngắn</span><textarea v-model="o.description" rows="3" /></label>
        </section>

        <section class="card stack">
          <h2 class="card__t">Thông số hiển thị trên landing</h2>
          <div class="row2">
            <label class="f"><span>{{ isLoan ? 'Cho vay từ (đ)' : 'Hạn mức thẻ từ (đ)' }}</span><input v-model="amountMin" inputmode="numeric"></label>
            <label class="f"><span>{{ isLoan ? 'Cho vay tối đa (đ)' : 'Hạn mức thẻ tối đa (đ)' }}</span><input v-model="amountMax" inputmode="numeric"><small v-if="errors.amount_max" class="err">{{ errors.amount_max }}</small></label>
          </div>
          <div class="row2">
            <label class="f"><span>{{ isLoan ? 'Lãi suất (chữ hiển thị)' : 'Ưu đãi lãi (chữ hiển thị)' }}</span><input v-model="o.rate_text" :placeholder="isLoan ? 'Từ 1,6%/tháng' : 'Miễn lãi đến 55 ngày'"></label>
            <label v-if="isLoan" class="f"><span>Lãi suất thấp nhất (%/năm) để tính trả góp</span><input v-model="o.rate_min" inputmode="decimal" placeholder="19.2"></label>
            <label v-else class="f"><span>Phí thường niên</span><input v-model="o.annual_fee" placeholder="Miễn phí năm đầu"></label>
          </div>
          <div v-if="isLoan" class="row2">
            <label class="f"><span>Kỳ hạn ngắn nhất (tháng)</span><input v-model="o.term_min" type="number" min="0"></label>
            <label class="f"><span>Kỳ hạn dài nhất (tháng)</span><input v-model="o.term_max" type="number" min="0"><small v-if="errors.term_max" class="err">{{ errors.term_max }}</small></label>
          </div>
          <div class="row2">
            <label class="f"><span>Độ tuổi</span><input v-model="o.age_text" placeholder="20 – 60 tuổi"></label>
            <label class="f"><span>Thời gian duyệt</span><input v-model="o.approval_text" placeholder="Trong 24 giờ"></label>
          </div>
        </section>

        <section class="card stack">
          <h2 class="card__t">Nội dung trang chi tiết</h2>
          <label class="f"><span>Điểm nổi bật, mỗi dòng một ý</span><textarea v-model="o.highlights" rows="4" /></label>
          <label class="f"><span>Điều kiện đăng ký, mỗi dòng một ý</span><textarea v-model="o.requirements" rows="4" /></label>
        </section>
      </div>

      <div class="stack">
        <section class="card stack">
          <h2 class="card__t">Xuất bản</h2>
          <label class="sw"><input v-model="o.active" type="checkbox"><span>Hiển thị trên website</span></label>
          <label class="f"><span>Thứ tự (nhỏ hiển thị trước)</span><input v-model="o.sort_order" type="number"></label>
          <button class="b b--primary b--block" type="submit" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Lưu gói' }}</button>
        </section>

        <section class="card stack">
          <h2 class="card__t">Link đối tác (affiliate)</h2>
          <label class="f"><span>Link chuyển khách sau khi đăng ký</span><input v-model="o.affiliate_url" placeholder="https://…"><small v-if="errors.affiliate_url" class="err">{{ errors.affiliate_url }}</small></label>
          <p class="hint">Có thể chèn biến để đối soát với đối tác: <code>{sub_id}</code> mã đăng ký (R123), <code>{utm_source}</code>, <code>{utm_campaign}</code>, <code>{landing}</code>. Để trống nếu bạn tự gọi tư vấn cho khách.</p>
        </section>

        <section class="card stack">
          <h2 class="card__t">Logo</h2>
          <img v-if="o.logo" :src="o.logo" alt="Logo hiện tại" class="logo-prev">
          <button v-if="o.logo" type="button" class="b b--ghost" @click="o.logo = ''">Bỏ logo</button>
          <label class="f"><span>{{ uploading ? 'Đang tải ảnh…' : 'Tải ảnh (PNG, JPG, WEBP, tối đa 1MB)' }}</span><input type="file" accept="image/png,image/jpeg,image/webp,image/gif" @change="upload"><small v-if="errors.logo" class="err">{{ errors.logo }}</small></label>
          <p class="hint">Không có logo thì website hiện chữ viết tắt tên đối tác.</p>
        </section>

        <section v-if="id" class="card">
          <button class="b b--danger-ghost b--block" type="button" @click="remove">{{ confirmDel ? 'Bấm lần nữa để xoá. Muốn tạm ngừng thì tắt Hiển thị.' : 'Xoá gói này' }}</button>
        </section>
      </div>
    </form>
  </div>
</template>

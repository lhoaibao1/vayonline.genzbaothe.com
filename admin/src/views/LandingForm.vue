<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, notify, ui } from '../api'

const route = useRoute()
const router = useRouter()
const id = route.params.id ? Number(route.params.id) : 0
const l = reactive<any>({ name: '', slug: '', headline: '', subheadline: '', offer_type: 'all', offer_ids: [], show_calculator: true, seo_title: '', seo_description: '', is_default: false, active: true })
const offers = ref<{ loan: any[]; card: any[] }>({ loan: [], card: [] })
const loaded = ref(!id)
const errors = ref<Record<string, string>>({})
const confirmDel = ref(false)
ui.navKey = '/landings'

Promise.all([api('/offers', { query: { type: 'loan' } }), api('/offers', { query: { type: 'card' } })]).then(([a, b]) => (offers.value = { loan: a.items, card: b.items }))
if (id) api(`/landings/${id}`).then((d) => { Object.assign(l, d); loaded.value = true })

const groups = computed(() => [
  { type: 'loan', name: 'Gói vay', items: offers.value.loan },
  { type: 'card', name: 'Gói thẻ', items: offers.value.card },
].filter((g) => l.offer_type === 'all' || l.offer_type === g.type))

async function save() {
  errors.value = {}
  const allowed = new Set(groups.value.flatMap((g) => g.items.map((o: any) => o.id)))
  try {
    await api(id ? `/landings/${id}` : '/landings', { method: id ? 'PUT' : 'POST', body: { ...l, offer_ids: l.offer_ids.filter((x: number) => allowed.has(x)) } })
    notify(`Đã lưu landing “${l.name}”.`)
    router.push('/landings')
  } catch (e: any) {
    errors.value = e.errors
    notify(e.message, 'err')
  }
}
async function remove() {
  if (!confirmDel.value) return (confirmDel.value = true)
  try {
    await api(`/landings/${id}`, { method: 'DELETE' })
    notify('Đã xoá landing.')
    router.push('/landings')
  } catch (e: any) {
    notify(e.message, 'err')
  }
}
</script>

<template>
  <div>
    <header class="mtop">
      <h1>{{ id ? `Sửa landing: ${l.name}` : 'Tạo landing' }}</h1>
      <RouterLink class="b b--ghost" to="/landings">Danh sách landing</RouterLink>
    </header>
    <form v-if="loaded" class="form-grid" @submit.prevent="save">
      <div class="stack">
        <section class="card stack">
          <h2 class="card__t">Nội dung</h2>
          <div class="row2">
            <label class="f"><span>Tên nội bộ (dùng trong báo cáo)</span><input v-model="l.name" required placeholder="Ví dụ: FB – Vay nhanh tháng 11"><small v-if="errors.name" class="err">{{ errors.name }}</small></label>
            <label class="f"><span>Đường dẫn</span><input v-model="l.slug" placeholder="tự tạo từ tên"><small v-if="errors.slug" class="err">{{ errors.slug }}</small><small class="hint">/lp/{{ l.slug || '…' }}</small></label>
          </div>
          <label class="f"><span>Tiêu đề chính</span><input v-model="l.headline" required maxlength="160"><small v-if="errors.headline" class="err">{{ errors.headline }}</small>
            <small class="hint">Đặt cụm muốn tô nổi bật trong *dấu sao*, ví dụ: Cần tiền gấp? *Chọn gói, duyệt liền.*</small></label>
          <label class="f"><span>Mô tả dưới tiêu đề</span><textarea v-model="l.subheadline" rows="2" maxlength="400" /></label>
          <label class="sw"><input v-model="l.show_calculator" type="checkbox"><span>Hiện công cụ tính khoản vay (khi landing có gói vay)</span></label>
        </section>

        <section class="card stack">
          <h2 class="card__t">Module hiển thị</h2>
          <label class="f"><span>Landing này giới thiệu</span>
            <select v-model="l.offer_type">
              <option value="all">Cả khoản vay và thẻ tín dụng (hai khu riêng)</option>
              <option value="loan">Chỉ khoản vay</option>
              <option value="card">Chỉ thẻ tín dụng</option>
            </select>
          </label>
          <fieldset v-for="g in groups" :key="g.type" class="pick">
            <legend>{{ g.name }} <span class="muted">(không chọn gói nào = hiện mọi gói đang bật)</span></legend>
            <label v-for="o in g.items" :key="o.id" class="sw"><input v-model="l.offer_ids" type="checkbox" :value="o.id"><span>{{ o.name }}<span v-if="!o.active" class="muted"> (đang ẩn)</span></span></label>
          </fieldset>
        </section>

        <section class="card stack">
          <h2 class="card__t">SEO và chia sẻ mạng xã hội</h2>
          <label class="f"><span>Tiêu đề trang</span><input v-model="l.seo_title" maxlength="80"></label>
          <label class="f"><span>Mô tả</span><textarea v-model="l.seo_description" rows="2" maxlength="200" /></label>
        </section>
      </div>
      <div class="stack">
        <section class="card stack">
          <h2 class="card__t">Xuất bản</h2>
          <label class="sw"><input v-model="l.active" type="checkbox"><span>Đang hoạt động</span></label>
          <label class="sw"><input v-model="l.is_default" type="checkbox"><span>Dùng làm trang chủ (/)</span></label>
          <button class="b b--primary b--block" type="submit">Lưu landing</button>
          <a v-if="id" class="b b--block" :href="l.is_default ? '/' : `/lp/${l.slug}`" target="_blank" rel="noopener">Xem trang</a>
        </section>
        <section v-if="id && !l.is_default" class="card">
          <button class="b b--danger-ghost b--block" type="button" @click="remove">{{ confirmDel ? 'Bấm lần nữa để xoá' : 'Xoá landing' }}</button>
        </section>
      </div>
    </form>
  </div>
</template>

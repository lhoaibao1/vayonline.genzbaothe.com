<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, can, label, notify, session, ui } from '../api'
import { age, dt, short } from '../util'

const route = useRoute()
const router = useRouter()
const c = ref<any>(null)
const leads = ref<any[]>([])
const errors = ref<Record<string, string>>({})
const confirmDel = ref(false)
const error = ref('')

async function load() {
  try {
    const d = await api(`/customers/${route.params.id}`)
    c.value = d.customer
    leads.value = d.leads
    ui.navKey = '/customers'
    document.title = `${d.customer.full_name} – GenZbaothe.com`
  } catch (e: any) {
    error.value = e.message
  }
}
load()

async function save() {
  errors.value = {}
  try {
    await api(`/customers/${c.value.id}`, { method: 'PUT', body: { ...c.value, birth_year: c.value.birth_year ? Number(c.value.birth_year) : null } })
    notify('Đã lưu thông tin khách hàng.')
  } catch (e: any) {
    errors.value = e.errors
    notify(e.message, 'err')
  }
}
async function remove() {
  if (!confirmDel.value) return (confirmDel.value = true)
  await api(`/customers/${c.value.id}`, { method: 'DELETE' })
  notify('Đã xoá khách hàng và toàn bộ đăng ký của khách.')
  router.push('/customers')
}
</script>

<template>
  <div>
    <header class="mtop">
      <h1>{{ c?.full_name || 'Khách hàng' }}</h1>
      <RouterLink class="b b--ghost" to="/customers">Danh sách khách hàng</RouterLink>
    </header>
    <p v-if="error" class="flash flash--error">{{ error }}</p>
    <div v-if="c" class="grid-side">
      <section class="card">
        <h2 class="card__t">Các lần đăng ký ({{ leads.length }})</h2>
        <ol class="timeline">
          <li v-for="r in leads" :key="r.id">
            <div class="timeline__head">
              <div>
                <RouterLink :to="`/leads/${r.id}`"><b>{{ r.offer_name }}</b></RouterLink>
                <span class="chip" :class="`chip--${r.offer_type}`">{{ r.offer_type === 'card' ? 'Thẻ tín dụng' : 'Khoản vay' }}</span>
                <div class="muted">R{{ r.id }} · {{ dt(r.created_at) }} · qua {{ r.landing_name || 'không rõ landing' }}</div>
              </div>
              <span class="st" :class="`st--${r.status}`">{{ label('status', r.status) }}</span>
            </div>
            <dl class="kv">
              <template v-if="r.offer_type === 'loan'">
                <div><dt>Muốn vay</dt><dd>{{ short(r.amount) }}{{ r.term ? ` · ${r.term} tháng` : '' }} · {{ label('purpose', r.purpose) }}</dd></div>
                <div><dt>Nhận lương qua</dt><dd>{{ label('salary_method', r.salary_method) }}</dd></div>
              </template>
              <template v-else>
                <div><dt>Nhu cầu thẻ</dt><dd>{{ label('card_need', r.card_need) }}</dd></div>
                <div><dt>Thẻ đang có</dt><dd>{{ label('has_card', r.has_card) }}</dd></div>
              </template>
              <div><dt>Lúc đăng ký</dt><dd>{{ age(r.birth_year) }} tuổi · {{ label('occupation', r.occupation) }} · thu nhập {{ label('income', r.income) }} · {{ r.province }}</dd></div>
              <div><dt>Sang đối tác</dt><dd>{{ r.clicked_out_at ? dt(r.clicked_out_at) : 'Chưa' }}</dd></div>
              <div v-if="r.note"><dt>Ghi chú</dt><dd>{{ r.note }}</dd></div>
            </dl>
          </li>
        </ol>
      </section>

      <section class="card">
        <h2 class="card__t">Hồ sơ khách hàng</h2>
        <form class="stack" @submit.prevent="save">
          <fieldset class="stack" :disabled="!can('leads.edit')">
            <label class="f"><span>Họ tên</span><input v-model="c.full_name" required><small v-if="errors.full_name" class="err">{{ errors.full_name }}</small></label>
            <label class="f"><span>Số điện thoại</span><input v-model="c.phone" required><small v-if="errors.phone" class="err">{{ errors.phone }}</small></label>
            <label class="f"><span>Email</span><input v-model="c.email" type="email"></label>
            <div class="row2">
              <label class="f"><span>Năm sinh</span><input v-model="c.birth_year" inputmode="numeric"></label>
              <label class="f"><span>Tỉnh/TP</span><select v-model="c.province"><option value="">—</option><option v-for="p in session.options?.provinces" :key="p">{{ p }}</option></select></label>
            </div>
            <div class="row2">
              <label class="f"><span>Công việc</span><select v-model="c.occupation"><option value="">—</option><option v-for="o in session.options?.occupation" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
              <label class="f"><span>Thu nhập</span><select v-model="c.income"><option value="">—</option><option v-for="o in session.options?.income" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
            </div>
            <label class="f"><span>Ghi chú chăm sóc</span><textarea v-model="c.note" rows="4" placeholder="Ví dụ: hẹn gọi lại 15h, đã gửi hồ sơ…" /></label>
            <button v-if="can('leads.edit')" class="b b--primary" type="submit">Lưu hồ sơ</button>
          </fieldset>
        </form>
        <p class="muted small">Tạo lúc {{ dt(c.created_at) }} · cập nhật {{ dt(c.updated_at) }}</p>
        <div class="quick">
          <a class="b" :href="`tel:${c.phone}`">Gọi khách</a>
          <a class="b" :href="`https://zalo.me/${c.phone}`" target="_blank" rel="noopener">Nhắn Zalo</a>
        </div>
        <button v-if="can('leads.delete')" class="b b--danger-ghost" type="button" @click="remove">
          {{ confirmDel ? `Bấm lần nữa để xoá khách và ${leads.length} đăng ký` : 'Xoá khách hàng' }}
        </button>
      </section>
    </div>
  </div>
</template>

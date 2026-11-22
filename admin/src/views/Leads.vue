<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, can, label, notify, session } from '../api'
import Pager from '../components/Pager.vue'
import StatusSelect from '../components/StatusSelect.vue'
import { age, dt, modText, modType, short, type Mod } from '../util'

const route = useRoute()
const router = useRouter()
const mod = computed(() => route.params.mod as Mod)
const type = computed(() => modType(mod.value))
const isLoan = computed(() => type.value === 'loan')
const T = computed(() => modText[mod.value])

const keys = ['q', 'status', 'offer', 'landing', 'source', 'from', 'to'] as const
const f = reactive<Record<(typeof keys)[number], string>>({ q: '', status: '', offer: '', landing: '', source: '', from: '', to: '' })
const page = ref(1)
const data = ref<any>(null)
const selected = ref<number[]>([])
const bulkAction = ref('')
const error = ref('')

// Bộ lọc lấy từ URL để chia sẻ được đường dẫn
function fromQuery() {
  for (const k of keys) f[k] = String(route.query[k] ?? '')
  page.value = Number(route.query.page || 1)
}
async function load() {
  error.value = ''
  try {
    data.value = await api('/leads', { query: { type: type.value, ...f, page: page.value } })
    selected.value = []
    document.title = `${T.value.leads} – GenZbaothe.com`
  } catch (e: any) {
    error.value = e.message
  }
}
watch(() => route.query, () => { fromQuery(); load() }, { immediate: true })

function apply(extra: Record<string, any> = {}) {
  const q: Record<string, string> = {}
  for (const k of keys) if (f[k]) q[k] = f[k]
  Object.assign(q, extra)
  for (const k of Object.keys(q)) if (!q[k] || q[k] === '1' && k === 'page') delete q[k]
  router.push({ path: route.path, query: q })
}
const hasFilter = computed(() => keys.some((k) => k !== 'status' && f[k]))
const exportUrl = computed(() => {
  const q = new URLSearchParams({ type: type.value })
  for (const k of keys) if (f[k]) q.set(k, f[k])
  return '/api/admin/leads/export?' + q
})
const allChecked = computed({
  get: () => !!data.value?.items.length && selected.value.length === data.value.items.length,
  set: (v: boolean) => (selected.value = v ? data.value.items.map((x: any) => x.id) : []),
})

async function runBulk() {
  if (!bulkAction.value || !selected.value.length) return
  if (bulkAction.value === 'delete' && !confirmDelete.value) {
    confirmDelete.value = true
    return
  }
  try {
    const r = await api('/leads/bulk', { method: 'POST', body: { ids: selected.value, action: bulkAction.value } })
    notify(bulkAction.value === 'delete' ? `Đã xoá ${r.affected} đăng ký.` : `Đã chuyển ${r.affected} đăng ký sang “${label('status', bulkAction.value)}”.`)
    bulkAction.value = ''
    confirmDelete.value = false
    load()
  } catch (e: any) {
    notify(e.message, 'err')
  }
}
const confirmDelete = ref(false)
</script>

<template>
  <div>
    <header class="mtop">
      <h1>{{ T.leads }}</h1>
      <RouterLink v-if="can('offers')" class="b b--ghost" :to="`/${mod}/offers`">Quản lý {{ T.offers.toLowerCase() }}</RouterLink>
      <a v-if="can('leads.export')" class="b" :href="exportUrl">Xuất Excel (CSV)</a>
    </header>

    <div class="content">
      <nav class="stabs" aria-label="Lọc theo trạng thái">
        <button type="button" :class="{ 'is-on': !f.status }" @click="f.status = ''; apply()">
          Tất cả <b>{{ Object.values(data?.counts ?? {}).reduce((a: number, b: any) => a + b, 0) }}</b>
        </button>
        <button v-for="o in session.options?.status" :key="o.value" type="button" :class="{ 'is-on': f.status === o.value }" @click="f.status = o.value; apply()">
          {{ o.label }} <b>{{ data?.counts?.[o.value] ?? 0 }}</b>
        </button>
      </nav>

      <form class="filters card" @submit.prevent="apply()">
        <label class="f f--grow"><span>Tìm kiếm</span><input v-model="f.q" placeholder="Tên, SĐT hoặc email"></label>
        <label class="f"><span>{{ T.offers }}</span><select v-model="f.offer"><option value="">Tất cả</option><option v-for="o in data?.offers" :key="o.id" :value="String(o.id)">{{ o.name }}</option></select></label>
        <label class="f"><span>Landing</span><select v-model="f.landing"><option value="">Tất cả landing</option><option v-for="l in data?.landings" :key="l.id" :value="String(l.id)">{{ l.name }}</option></select></label>
        <label v-if="data?.sources?.length" class="f"><span>Nguồn</span><select v-model="f.source"><option value="">Mọi nguồn</option><option v-for="s in data.sources" :key="s">{{ s }}</option></select></label>
        <label class="f"><span>Từ ngày</span><input v-model="f.from" type="date"></label>
        <label class="f"><span>Đến ngày</span><input v-model="f.to" type="date"></label>
        <div class="filters__btns">
          <button class="b b--primary" type="submit">Lọc</button>
          <button v-if="hasFilter" class="b b--ghost" type="button" @click="router.push(route.path)">Bỏ lọc</button>
        </div>
      </form>

      <p v-if="error" class="flash flash--error">{{ error }}</p>

      <section v-if="data" class="card card--flush">
        <div class="bulk">
          <span><b>{{ data.total.toLocaleString('vi-VN') }}</b> {{ T.lead }}</span>
          <div v-if="can('leads.edit') && selected.length" class="bulk__act">
            <span>Đã chọn {{ selected.length }}</span>
            <select v-model="bulkAction" aria-label="Thao tác với đăng ký đã chọn" @change="confirmDelete = false">
              <option value="">Chọn thao tác…</option>
              <option v-for="o in session.options?.status" :key="o.value" :value="o.value">Chuyển sang: {{ o.label }}</option>
              <option v-if="can('leads.delete')" value="delete">Xoá đăng ký đã chọn</option>
            </select>
            <button class="b" :class="{ 'b--danger-ghost': confirmDelete }" type="button" :disabled="!bulkAction" @click="runBulk">
              {{ confirmDelete ? `Bấm lần nữa để xoá ${selected.length} đăng ký` : 'Áp dụng' }}
            </button>
          </div>
        </div>

        <p v-if="!data.items.length" class="empty-st">Chưa có {{ T.lead }} nào khớp bộ lọc. <RouterLink :to="route.path">Xem tất cả</RouterLink></p>
        <div v-else class="tscroll">
          <table class="t t--leads">
            <thead>
              <tr>
                <th v-if="can('leads.edit')" class="cb"><input v-model="allChecked" type="checkbox" aria-label="Chọn tất cả"></th>
                <th>Mã / thời gian</th><th>Khách hàng</th><th>Hồ sơ</th><th>{{ isLoan ? 'Nhu cầu vay' : 'Nhu cầu thẻ' }}</th><th>Gói / nguồn</th><th>Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in data.items" :key="r.id">
                <td v-if="can('leads.edit')" class="cb"><input v-model="selected" type="checkbox" :value="r.id" :aria-label="`Chọn R${r.id}`"></td>
                <td class="nowrap"><RouterLink :to="`/leads/${r.id}`"><b>R{{ r.id }}</b></RouterLink><div class="muted">{{ dt(r.created_at) }}</div></td>
                <td>
                  <RouterLink :to="`/leads/${r.id}`"><b>{{ r.full_name }}</b></RouterLink>
                  <div><a class="tel" :href="`tel:${r.phone}`">{{ r.phone }}</a></div>
                  <div v-if="r.email" class="muted">{{ r.email }}</div>
                </td>
                <td>
                  {{ age(r.birth_year) }} tuổi · {{ r.province }}
                  <div class="muted">{{ label('occupation', r.occupation) }}</div>
                  <div class="muted">Thu nhập {{ label('income', r.income) }}</div>
                </td>
                <td v-if="isLoan">
                  <b>{{ short(r.amount) }}</b>{{ r.term ? ` · ${r.term} tháng` : '' }}
                  <div class="muted">{{ label('purpose', r.purpose) }}</div>
                  <div class="muted">Lương: {{ label('salary_method', r.salary_method).toLowerCase() }}</div>
                </td>
                <td v-else>
                  <b>{{ label('card_need', r.card_need) }}</b>
                  <div class="muted">{{ label('has_card', r.has_card) }}</div>
                </td>
                <td>
                  {{ r.offer_name }}
                  <div class="muted">{{ r.landing_name || '—' }}{{ r.utm_source ? ' · ' + r.utm_source : '' }}</div>
                  <div class="muted">{{ r.clicked_out_at ? '✓ Đã sang trang đối tác' : 'Chưa sang trang đối tác' }}</div>
                </td>
                <td>
                  <StatusSelect :id="r.id" :status="r.status" @changed="(s) => (r.status = s)" />
                  <div v-if="r.note" class="muted note" :title="r.note">{{ r.note.length > 60 ? r.note.slice(0, 60) + '…' : r.note }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <Pager v-if="data" :page="data.page" :total="data.total" :per="data.per" @go="(p) => apply({ page: String(p) })" />
    </div>
  </div>
</template>

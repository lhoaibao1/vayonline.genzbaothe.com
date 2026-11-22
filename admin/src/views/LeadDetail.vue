<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, can, label, notify, session, ui } from '../api'
import { age, dt, fmt, modText, typeMod } from '../util'

const route = useRoute()
const lead = ref<any>(null)
const others = ref<any[]>([])
const error = ref('')
const status = ref('')
const note = ref('')
const saving = ref(false)

const mod = computed(() => typeMod(lead.value?.offer_type))
const isLoan = computed(() => lead.value?.offer_type === 'loan')

async function load() {
  try {
    const d = await api(`/leads/${route.params.id}`)
    lead.value = d.lead
    others.value = d.others
    status.value = d.lead.status
    note.value = d.lead.note
    ui.navKey = `/${mod.value}/leads`
    document.title = `Đăng ký R${d.lead.id} – GenZbaothe.com`
  } catch (e: any) {
    error.value = e.message
  }
}
load()

async function save() {
  saving.value = true
  try {
    await api(`/leads/${lead.value.id}`, { method: 'PATCH', body: { status: status.value, note: note.value } })
    lead.value.status = status.value
    lead.value.note = note.value
    notify('Đã lưu trạng thái và ghi chú.')
  } catch (e: any) {
    notify(e.message, 'err')
  } finally {
    saving.value = false
  }
}
const utm = computed(() => [lead.value?.utm_source, lead.value?.utm_medium, lead.value?.utm_campaign, lead.value?.utm_content].filter(Boolean).join(' / ') || '(trực tiếp)')
</script>

<template>
  <div>
    <header class="mtop">
      <h1>{{ lead ? `Đăng ký R${lead.id}` : 'Đăng ký' }}</h1>
      <RouterLink v-if="lead" class="b b--ghost" :to="`/${mod}/leads`">Danh sách {{ modText[mod].lead }}</RouterLink>
    </header>
    <p v-if="error" class="flash flash--error">{{ error }}</p>

    <div v-if="lead" class="grid-side">
      <div class="stack">
        <section class="card">
          <div class="card__head">
            <div>
              <span class="chip" :class="`chip--${lead.offer_type}`">{{ isLoan ? 'Khoản vay' : 'Thẻ tín dụng' }}</span>
              <h2 class="card__t" style="margin: 8px 0 2px">{{ lead.offer_name }}</h2>
              <p class="muted">Gửi lúc {{ dt(lead.created_at) }} · qua {{ lead.landing_name || 'không rõ landing' }}</p>
            </div>
            <span class="st" :class="`st--${lead.status}`">{{ label('status', lead.status) }}</span>
          </div>
        </section>

        <section class="card">
          <h2 class="card__t">Thông tin khách điền khi đăng ký</h2>
          <dl class="kv kv--wide">
            <div><dt>Họ tên</dt><dd><b>{{ lead.full_name }}</b></dd></div>
            <div><dt>Số điện thoại</dt><dd><a class="tel" :href="`tel:${lead.phone}`">{{ lead.phone }}</a></dd></div>
            <div><dt>Năm sinh</dt><dd>{{ lead.birth_year }} ({{ age(lead.birth_year) }} tuổi)</dd></div>
            <div><dt>Email</dt><dd>{{ lead.email || '—' }}</dd></div>
            <div><dt>Tỉnh / thành phố</dt><dd>{{ lead.province }}</dd></div>
            <div><dt>Công việc</dt><dd>{{ label('occupation', lead.occupation) }}</dd></div>
            <div><dt>Thu nhập / tháng</dt><dd>{{ label('income', lead.income) }}</dd></div>
          </dl>
        </section>

        <section class="card">
          <h2 class="card__t">{{ isLoan ? 'Nhu cầu vay' : 'Nhu cầu mở thẻ' }}</h2>
          <dl v-if="isLoan" class="kv kv--wide">
            <div><dt>Số tiền muốn vay</dt><dd><b>{{ lead.amount ? fmt(lead.amount) + 'đ' : '—' }}</b></dd></div>
            <div><dt>Thời gian trả</dt><dd>{{ lead.term ? lead.term + ' tháng' : '—' }}</dd></div>
            <div><dt>Mục đích vay</dt><dd>{{ label('purpose', lead.purpose) || '—' }}</dd></div>
            <div><dt>Nhận lương qua</dt><dd>{{ label('salary_method', lead.salary_method) || '—' }}</dd></div>
          </dl>
          <dl v-else class="kv kv--wide">
            <div><dt>Thẻ đang có</dt><dd>{{ label('has_card', lead.has_card) || '—' }}</dd></div>
            <div><dt>Dùng thẻ chủ yếu để</dt><dd><b>{{ label('card_need', lead.card_need) || '—' }}</b></dd></div>
          </dl>
        </section>

        <section class="card">
          <h2 class="card__t">Sự đồng ý xử lý dữ liệu</h2>
          <dl class="kv kv--wide">
            <div><dt>Thời điểm đồng ý</dt><dd>{{ lead.consent_at ? dt(lead.consent_at) : 'Không có (đăng ký trước khi có chính sách)' }}</dd></div>
            <div><dt>Phiên bản chính sách</dt><dd class="break">{{ lead.consent_versions || '—' }}</dd></div>
            <div v-if="lead.anonymized_at"><dt>Đã ẩn danh hoá</dt><dd>{{ dt(lead.anonymized_at) }}</dd></div>
          </dl>
        </section>

        <section class="card">
          <h2 class="card__t">Nguồn truy cập</h2>
          <dl class="kv kv--wide">
            <div><dt>Sang trang đối tác</dt><dd>{{ lead.clicked_out_at ? dt(lead.clicked_out_at) : 'Chưa' }}</dd></div>
            <div><dt>UTM</dt><dd>{{ utm }}</dd></div>
            <div v-if="lead.referrer"><dt>Trang giới thiệu</dt><dd class="break">{{ lead.referrer }}</dd></div>
            <div><dt>IP / thiết bị</dt><dd class="break muted">{{ lead.ip }} · {{ lead.user_agent.slice(0, 90) }}</dd></div>
          </dl>
        </section>
      </div>

      <div class="stack">
        <section class="card stack">
          <h2 class="card__t" style="margin: 0">Xử lý</h2>
          <form v-if="can('leads.edit')" class="stack" @submit.prevent="save">
            <label class="f"><span>Trạng thái</span>
              <select v-model="status"><option v-for="o in session.options?.status" :key="o.value" :value="o.value">{{ o.label }}</option></select>
            </label>
            <label class="f"><span>Ghi chú</span><textarea v-model="note" rows="3" maxlength="1000" placeholder="Ví dụ: hẹn gọi lại 15h" /></label>
            <button class="b b--primary" type="submit" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Lưu' }}</button>
          </form>
          <p v-else class="muted">{{ lead.note || 'Chưa có ghi chú.' }}</p>
          <div class="quick">
            <a class="b" :href="`tel:${lead.phone}`">Gọi khách</a>
            <a class="b" :href="`https://zalo.me/${lead.phone}`" target="_blank" rel="noopener">Nhắn Zalo</a>
          </div>
        </section>

        <section class="card">
          <h2 class="card__t">Khách này còn đăng ký</h2>
          <p v-if="!others.length" class="muted">Chưa có đăng ký nào khác.</p>
          <ul class="mini-list">
            <li v-for="o in others" :key="o.id">
              <RouterLink :to="`/leads/${o.id}`">{{ o.offer_name }}</RouterLink>
              <span class="chip" :class="`chip--${o.offer_type}`">{{ o.offer_type === 'card' ? 'Thẻ' : 'Vay' }}</span>
              <div class="muted">R{{ o.id }} · {{ dt(o.created_at) }} · {{ label('status', o.status) }}</div>
            </li>
          </ul>
          <RouterLink :to="`/customers/${lead.customer_id}`">Mở hồ sơ khách hàng</RouterLink>
        </section>
      </div>
    </div>
  </div>
</template>

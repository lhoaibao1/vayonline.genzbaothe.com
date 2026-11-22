<script setup lang="ts">
import { ref, watch } from 'vue'
import { api, can, notify } from '../api'
import { dt } from '../util'

const status = ref('')
const data = ref<any>(null)
const editing = ref<Record<number, { status: string; note: string }>>({})

async function load() {
  data.value = await api('/data-requests', { query: { status: status.value } })
  editing.value = Object.fromEntries(data.value.items.map((r: any) => [r.id, { status: r.status, note: r.note }]))
}
watch(status, load, { immediate: true })

const label = (list: any[], v: string) => list?.find((o: any) => o.value === v)?.label || v
const total = () => Object.values<number>(data.value?.counts ?? {}).reduce((a, b) => a + b, 0)

async function save(r: any) {
  try {
    await api(`/data-requests/${r.id}`, { method: 'PATCH', body: editing.value[r.id] })
    notify(`Đã cập nhật yêu cầu YC${r.id}.`)
    load()
  } catch (e: any) {
    notify(e.message, 'err')
  }
}
</script>

<template>
  <div>
    <header class="mtop"><h1>Yêu cầu về dữ liệu cá nhân</h1></header>
    <div class="content">
      <p class="muted">
        Khách gửi tại trang <code>/yeu-cau-du-lieu</code>. Hãy gọi xác minh chủ số điện thoại trước khi xử lý.
        Với yêu cầu xoá hoặc rút lại đồng ý: mở hồ sơ khách và bấm <b>Ẩn danh hoá dữ liệu</b>, đồng thời báo cho đối tác đã nhận hồ sơ.
      </p>
      <nav class="stabs" aria-label="Lọc theo trạng thái">
        <button type="button" :class="{ 'is-on': !status }" @click="status = ''">Tất cả <b>{{ total() }}</b></button>
        <button v-for="o in data?.statuses" :key="o.value" type="button" :class="{ 'is-on': status === o.value }" @click="status = o.value">
          {{ o.label }} <b>{{ data?.counts?.[o.value] ?? 0 }}</b>
        </button>
      </nav>

      <section v-if="data" class="card card--flush">
        <p v-if="!data.items.length" class="empty-st">Chưa có yêu cầu nào.</p>
        <div v-else class="tscroll">
          <table class="t">
            <thead><tr><th>Mã / thời gian</th><th>Người gửi</th><th>Yêu cầu</th><th>Xử lý</th></tr></thead>
            <tbody>
              <tr v-for="r in data.items" :key="r.id">
                <td class="nowrap"><b>YC{{ r.id }}</b><div class="muted">{{ dt(r.created_at) }}</div></td>
                <td>
                  <b>{{ r.full_name }}</b>
                  <div>{{ r.phone || '—' }}</div>
                  <div v-if="r.email" class="muted">{{ r.email }}</div>
                  <RouterLink v-if="r.customer_id" :to="`/customers/${r.customer_id}`">Mở hồ sơ khách</RouterLink>
                  <div v-else class="muted">Không khớp khách nào theo SĐT</div>
                </td>
                <td style="max-width: 320px">
                  <span class="chip" :class="{ 'chip--card': r.type === 'delete' || r.type === 'withdraw' }">{{ label(data.types, r.type) }}</span>
                  <div v-if="r.content" style="margin-top: 6px; white-space: pre-line">{{ r.content }}</div>
                </td>
                <td>
                  <form v-if="can('leads.edit')" class="stack" style="min-width: 240px" @submit.prevent="save(r)">
                    <select v-model="editing[r.id].status" aria-label="Trạng thái" class="st-select">
                      <option v-for="o in data.statuses" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                    <textarea v-model="editing[r.id].note" rows="2" class="mini-input" placeholder="Ghi chú xử lý, ví dụ: đã xác minh qua điện thoại" />
                    <button class="b" type="submit">Lưu</button>
                  </form>
                  <template v-else>{{ label(data.statuses, r.status) }}<div class="muted">{{ r.note }}</div></template>
                  <div v-if="r.handled_by_name" class="muted small">Xử lý bởi {{ r.handled_by_name }} · {{ dt(r.updated_at) }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { api, notify } from '../api'
import { dt } from '../util'

const items = ref<any[]>([])
const current = ref<any>(null)
const saving = ref(false)

async function load(keep?: string) {
  items.value = (await api('/pages')).items
  current.value = { ...(items.value.find((p) => p.slug === keep) || items.value[0]) }
}
load()

const original = computed(() => items.value.find((p) => p.slug === current.value?.slug))
const dirty = computed(() => original.value && (original.value.content !== current.value.content || original.value.title !== current.value.title))

async function save() {
  saving.value = true
  try {
    await api(`/pages/${current.value.slug}`, { method: 'PUT', body: current.value })
    notify(original.value.content !== current.value.content ? 'Đã lưu. Phiên bản chính sách tăng lên, các đăng ký mới ghi nhận phiên bản này.' : 'Đã lưu tiêu đề.')
    load(current.value.slug)
  } catch (e: any) {
    notify(e.message, 'err')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <header class="mtop">
      <h1>Trang chính sách</h1>
      <a v-if="current" class="b b--ghost" :href="`/${current.slug}`" target="_blank" rel="noopener">Xem trên website</a>
    </header>
    <div class="content">
      <nav class="stabs" aria-label="Chọn trang">
        <button v-for="p in items" :key="p.slug" type="button" :class="{ 'is-on': current?.slug === p.slug }" @click="current = { ...p }">
          {{ p.title }} <b>v{{ p.version }}</b>
        </button>
      </nav>
      <form v-if="current" class="form-grid" @submit.prevent="save">
        <section class="card stack">
          <label class="f"><span>Tiêu đề</span><input v-model="current.title" required></label>
          <label class="f"><span>Nội dung</span><textarea v-model="current.content" rows="28" class="mono" /></label>
        </section>
        <div class="stack">
          <section class="card stack">
            <h2 class="card__t" style="margin: 0">Phiên bản {{ original?.version }}</h2>
            <p class="muted small" style="margin: 0">Sửa lần cuối {{ dt(original?.updated_at) }}</p>
            <p class="hint">Mỗi lần đổi nội dung, phiên bản tăng lên. Từng đăng ký lưu lại phiên bản khách đã đồng ý, dùng làm bằng chứng khi cần.</p>
            <button class="b b--primary b--block" type="submit" :disabled="saving || !dirty">{{ saving ? 'Đang lưu…' : dirty ? 'Lưu thay đổi' : 'Chưa có thay đổi' }}</button>
          </section>
          <section class="card stack">
            <h2 class="card__t" style="margin: 0">Cách viết</h2>
            <ul class="hint" style="margin: 0; padding-left: 18px; display: grid; gap: 4px">
              <li><code>## Tiêu đề mục</code></li>
              <li><code>- ý</code> cho danh sách, <code>1. ý</code> cho danh sách đánh số</li>
              <li><code>**chữ đậm**</code>, <code>[chữ](/duong-dan)</code> cho link</li>
              <li>Dòng trống để tách đoạn</li>
            </ul>
            <p class="hint">Biến tự điền từ Cài đặt: <code v-pre>{{site_name}}</code> <code v-pre>{{company_name}}</code> <code v-pre>{{tax_code}}</code> <code v-pre>{{company_address}}</code>
              <code v-pre>{{contact_email}}</code> <code v-pre>{{privacy_email}}</code> <code v-pre>{{hotline}}</code> <code v-pre>{{updated_at}}</code>. Biến chưa điền hiện “[chưa cập nhật]” màu đỏ trên website.</p>
            <p class="hint">Nội dung mẫu chỉ để tham khảo. Hãy nhờ luật sư rà soát trước khi chạy thật.</p>
          </section>
        </div>
      </form>
    </div>
  </div>
</template>

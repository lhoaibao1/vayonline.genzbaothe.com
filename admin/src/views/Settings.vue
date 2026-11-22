<script setup lang="ts">
import { ref } from 'vue'
import { api, notify } from '../api'

const s = ref<Record<string, string> | null>(null)
const errors = ref<Record<string, string>>({})
api('/settings').then((d) => (s.value = d))

async function save() {
  errors.value = {}
  try {
    await api('/settings', { method: 'PUT', body: s.value })
    notify('Đã lưu cài đặt. Website cập nhật trong khoảng 1 phút.')
  } catch (e: any) {
    errors.value = e.errors
    notify(e.message, 'err')
  }
}
</script>

<template>
  <div>
    <header class="mtop"><h1>Cài đặt website</h1></header>
    <form v-if="s" class="form-grid" @submit.prevent="save">
      <div class="stack">
        <section class="card stack">
          <h2 class="card__t">Thông tin website</h2>
          <div class="row2">
            <label class="f"><span>Tên website</span><input v-model="s.site_name" required><small v-if="errors.site_name" class="err">{{ errors.site_name }}</small></label>
            <label class="f"><span>Khẩu hiệu (mô tả mặc định)</span><input v-model="s.site_tagline"></label>
          </div>
          <div class="row2">
            <label class="f"><span>Hotline</span><input v-model="s.hotline" placeholder="1900 xxxx"></label>
            <label class="f"><span>Số Zalo</span><input v-model="s.zalo"></label>
          </div>
          <label class="f"><span>Email liên hệ</span><input v-model="s.contact_email" type="email"></label>
          <label class="f"><span>Giới thiệu ở chân trang</span><textarea v-model="s.company_info" rows="3" /></label>
          <label class="f"><span>Tuyên bố miễn trừ trách nhiệm</span><textarea v-model="s.disclaimer" rows="3" /></label>
        </section>
        <section class="card stack">
          <h2 class="card__t">Câu hỏi thường gặp</h2>
          <label class="f"><span>Mỗi dòng một câu, dạng <code>Câu hỏi|Câu trả lời</code></span><textarea v-model="s.faq" rows="8" /></label>
        </section>
      </div>
      <div class="stack">
        <section class="card stack">
          <h2 class="card__t">Mã theo dõi</h2>
          <label class="f"><span>Chèn vào &lt;head&gt; (Google Analytics, GTM, Facebook Pixel, TikTok Pixel…)</span><textarea v-model="s.head_scripts" rows="7" class="mono" /></label>
          <label class="f"><span>Chèn cuối &lt;body&gt;</span><textarea v-model="s.body_scripts" rows="4" class="mono" /></label>
          <p class="hint">Khi khách gửi đăng ký thành công, website gửi sự kiện <code>lead_submit</code> (kèm <code>offer_type</code> là loan hoặc card) vào dataLayer và sự kiện <code>Lead</code> cho Facebook Pixel nếu có.</p>
          <button class="b b--primary b--block" type="submit">Lưu cài đặt</button>
        </section>
      </div>
    </form>
  </div>
</template>

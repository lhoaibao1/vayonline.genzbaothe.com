<script setup lang="ts">
// Chủ thể dữ liệu gửi yêu cầu: xem, sửa, xoá dữ liệu, rút lại đồng ý…
const { data: site } = await useSite()
const { data: opts } = await useOptions()
useSeoMeta({ title: () => `Yêu cầu về dữ liệu cá nhân – ${site.value?.site_name}` })

const f = reactive({ type: '', full_name: '', phone: '', email: '', content: '', website: '' })
const errors = ref<Record<string, string>>({})
const message = ref('')
const sending = ref(false)
const code = ref('')
const needContent = computed(() => f.type === 'correct' || f.type === 'other')

async function submit() {
  errors.value = {}
  message.value = ''
  const e: Record<string, string> = {}
  if (!f.type) e.type = 'Chọn loại yêu cầu.'
  if (f.full_name.trim().length < 2) e.full_name = 'Nhập họ tên đã dùng khi đăng ký.'
  if (!/^0[35789]\d{8}$/.test(f.phone.replace(/\D/g, '').replace(/^84/, '0'))) e.phone = 'Nhập số điện thoại đã dùng khi đăng ký (10 số).'
  if (needContent.value && f.content.trim().length < 5) e.content = 'Mô tả nội dung bạn cần, ví dụ thông tin nào cần sửa.'
  if (Object.keys(e).length) { errors.value = e; return }
  sending.value = true
  try {
    const r = await $fetch<{ code: string }>('/api/public/data-requests', { method: 'POST', body: f })
    code.value = r.code
  } catch (err: any) {
    errors.value = err?.data?.errors || {}
    message.value = err?.data?.message || 'Không gửi được do mất kết nối. Kiểm tra mạng rồi gửi lại.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <section class="legal">
    <div class="wrap legal__in">
      <nav class="crumbs" aria-label="Vị trí"><NuxtLink to="/">Trang chủ</NuxtLink> / Yêu cầu về dữ liệu cá nhân</nav>
      <h1>Yêu cầu về dữ liệu cá nhân</h1>
      <nav class="legal__tabs" aria-label="Các chính sách">
        <NuxtLink v-for="p in site?.pages" :key="p.slug" :to="`/${p.slug}`">{{ p.title }}</NuxtLink>
        <NuxtLink to="/yeu-cau-du-lieu" aria-current="page">Yêu cầu về dữ liệu cá nhân</NuxtLink>
      </nav>

      <div v-if="code" class="req-done">
        <h2>Đã nhận yêu cầu {{ code }}</h2>
        <p>Chúng tôi sẽ liên hệ qua số điện thoại bạn cung cấp để xác minh và xử lý trong thời hạn pháp luật quy định. Hãy giữ mã yêu cầu để tra cứu khi cần.</p>
        <NuxtLink class="btn btn--gold" to="/">Về trang chủ</NuxtLink>
      </div>

      <form v-else class="req-form" novalidate @submit.prevent="submit">
        <p class="legal__lead">
          Bạn có thể yêu cầu xem, sửa, xoá dữ liệu đã đăng ký hoặc rút lại sự đồng ý. Để bảo vệ bạn, chúng tôi sẽ xác minh bạn là chủ số điện thoại trước khi xử lý.
          Xem thêm <NuxtLink to="/bao-ve-du-lieu-ca-nhan">Thông báo xử lý dữ liệu cá nhân</NuxtLink>.
        </p>
        <div class="hp" aria-hidden="true"><label>Website <input v-model="f.website" tabindex="-1" autocomplete="off"></label></div>
        <fieldset class="req-types" :class="{ 'has-err': errors.type }">
          <legend>Bạn muốn</legend>
          <label v-for="o in opts?.data_request_type" :key="o.value" class="req-type">
            <input v-model="f.type" type="radio" name="type" :value="o.value"><span>{{ o.label }}</span>
          </label>
          <small class="field__err">{{ errors.type }}</small>
        </fieldset>
        <div class="field-row">
          <label class="field" :class="{ 'has-err': errors.full_name }">
            <span>Họ tên đã đăng ký</span><input v-model="f.full_name" autocomplete="name" maxlength="80">
            <small class="field__err">{{ errors.full_name }}</small>
          </label>
          <label class="field" :class="{ 'has-err': errors.phone }">
            <span>Số điện thoại đã đăng ký</span><input v-model="f.phone" type="tel" inputmode="numeric" autocomplete="tel" maxlength="12">
            <small class="field__err">{{ errors.phone }}</small>
          </label>
        </div>
        <label class="field">
          <span>Email nhận phản hồi <em>(không bắt buộc)</em></span><input v-model="f.email" type="email" autocomplete="email">
        </label>
        <label class="field" :class="{ 'has-err': errors.content }">
          <span>Nội dung {{ needContent ? '' : '(không bắt buộc)' }}</span>
          <textarea v-model="f.content" rows="4" maxlength="2000" :placeholder="f.type === 'correct' ? 'Ví dụ: năm sinh đúng là 1995' : 'Mô tả thêm nếu cần'" />
          <small class="field__err">{{ errors.content }}</small>
        </label>
        <p class="form-msg" role="alert">{{ message }}</p>
        <button class="btn btn--gold btn--lg" type="submit" :disabled="sending">{{ sending ? 'Đang gửi…' : 'Gửi yêu cầu' }}</button>
      </form>
    </div>
  </section>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { api, notify, session } from '../api'

const f = reactive({ name: session.me?.admin.name || '', password: '', current_password: '' })
const errors = ref<Record<string, string>>({})

async function save() {
  errors.value = {}
  try {
    await api('/me', { method: 'PUT', body: f })
    if (session.me) session.me.admin.name = f.name
    f.password = ''
    f.current_password = ''
    notify('Đã cập nhật tài khoản.')
  } catch (e: any) {
    errors.value = e.errors
    notify(e.message, 'err')
  }
}
</script>

<template>
  <div>
    <header class="mtop"><h1>Tài khoản của tôi</h1></header>
    <form class="card stack narrow" @submit.prevent="save">
      <p class="muted">Email đăng nhập: <b>{{ session.me?.admin.email }}</b> · Vai trò: {{ session.me?.role_label }}</p>
      <label class="f"><span>Họ tên</span><input v-model="f.name" required><small v-if="errors.name" class="err">{{ errors.name }}</small></label>
      <label class="f"><span>Mật khẩu mới (để trống nếu giữ nguyên)</span><input v-model="f.password" type="password" autocomplete="new-password"><small v-if="errors.password" class="err">{{ errors.password }}</small></label>
      <label class="f"><span>Mật khẩu hiện tại (bắt buộc để lưu)</span><input v-model="f.current_password" type="password" required autocomplete="current-password"><small v-if="errors.current_password" class="err">{{ errors.current_password }}</small></label>
      <div class="btns"><button class="b b--primary" type="submit">Lưu thay đổi</button></div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, loadSession, session } from '../api'

const router = useRouter()
const f = reactive({ name: '', email: '', password: '' })
const errors = ref<Record<string, string>>({})
const message = ref('')

async function submit() {
  errors.value = {}
  message.value = ''
  try {
    await api('/setup', { method: 'POST', body: f })
    session.me = null
    await loadSession()
    router.replace('/')
  } catch (e: any) {
    errors.value = e.errors
    message.value = e.message
  }
}
</script>

<template>
  <div class="auth">
    <form class="auth__box" @submit.prevent="submit">
      <p class="auth__brand"><span class="brand__mark" aria-hidden="true" />GenZbaothe.com</p>
      <h1>Tạo tài khoản quản trị</h1>
      <p class="muted">Website chưa có tài khoản nào. Tài khoản bạn tạo ở đây có toàn quyền quản trị.</p>
      <div v-if="message && !Object.keys(errors).length" class="flash flash--error">{{ message }}</div>
      <label class="f"><span>Họ tên</span><input v-model="f.name" required><small v-if="errors.name" class="err">{{ errors.name }}</small></label>
      <label class="f"><span>Email đăng nhập</span><input v-model="f.email" type="email" required><small v-if="errors.email" class="err">{{ errors.email }}</small></label>
      <label class="f"><span>Mật khẩu (ít nhất 8 ký tự)</span><input v-model="f.password" type="password" minlength="8" required autocomplete="new-password"><small v-if="errors.password" class="err">{{ errors.password }}</small></label>
      <button class="b b--primary b--block" type="submit">Tạo tài khoản</button>
    </form>
  </div>
</template>

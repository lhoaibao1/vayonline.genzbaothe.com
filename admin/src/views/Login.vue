<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, loadSession, session } from '../api'

const route = useRoute()
const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')
const busy = ref(false)

async function submit() {
  error.value = ''
  busy.value = true
  try {
    await api('/login', { method: 'POST', body: { email: email.value, password: password.value } })
    session.me = null
    await loadSession()
    const next = String(route.query.next || '')
    router.replace(next.startsWith('/') && !next.startsWith('//') ? next.replace(/^\/admin/, '') || '/' : '/')
  } catch (e: any) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="auth">
    <form class="auth__box" @submit.prevent="submit">
      <p class="auth__brand"><span class="brand__mark" aria-hidden="true" />GenZbaothe.com</p>
      <h1>Đăng nhập quản trị</h1>
      <div v-if="error" class="flash flash--error" role="alert">{{ error }}</div>
      <label class="f"><span>Email</span><input v-model="email" type="email" required autofocus autocomplete="username"></label>
      <label class="f"><span>Mật khẩu</span><input v-model="password" type="password" required autocomplete="current-password"></label>
      <button class="b b--primary b--block" type="submit" :disabled="busy">{{ busy ? 'Đang đăng nhập…' : 'Đăng nhập' }}</button>
    </form>
  </div>
</template>

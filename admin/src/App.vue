<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, can, session, toast, ui } from './api'

declare const __DEMO__: boolean
const demo = __DEMO__
const route = useRoute()
const router = useRouter()
const navOpen = ref(false)
const counts = ref({ loan: 0, card: 0, req: 0 })

watch(() => route.path, () => { navOpen.value = false; ui.navKey = '' })

// Số đăng ký mới trên menu, làm mới mỗi khi đổi trang
watch(() => [route.fullPath, session.me], async () => {
  if (!session.me || !can('leads.view')) return
  try {
    const [l, c, d] = await Promise.all([
      api('/leads', { query: { type: 'loan', status: 'new', page: 1 } }),
      api('/leads', { query: { type: 'card', status: 'new', page: 1 } }),
      api('/data-requests', { query: { status: 'new' } }),
    ])
    counts.value = { loan: l.total, card: c.total, req: d.counts?.new ?? 0 }
  } catch { /* bỏ qua */ }
}, { immediate: true })

const nav = computed(() => [
  { group: '', items: [{ to: '/', label: 'Tổng quan', perm: 'leads.view', exact: true }] },
  { group: 'Khoản vay', items: [
    { to: '/loans/leads', label: 'Đăng ký vay', perm: 'leads.view', count: counts.value.loan },
    { to: '/loans/offers', label: 'Gói vay', perm: 'offers' },
  ] },
  { group: 'Thẻ tín dụng', items: [
    { to: '/cards/leads', label: 'Đăng ký mở thẻ', perm: 'leads.view', count: counts.value.card, pink: true },
    { to: '/cards/offers', label: 'Gói thẻ', perm: 'offers' },
  ] },
  { group: 'Chung', items: [
    { to: '/customers', label: 'Khách hàng', perm: 'leads.view' },
    { to: '/landings', label: 'Landing page', perm: 'landings' },
  ] },
  { group: 'Dữ liệu cá nhân', items: [
    { to: '/data-requests', label: 'Yêu cầu dữ liệu', perm: 'leads.view', count: counts.value.req, pink: true },
    { to: '/pages', label: 'Trang chính sách', perm: 'settings' },
  ] },
  { group: 'Hệ thống', items: [
    { to: '/users', label: 'Tài khoản quản trị', perm: 'admins' },
    { to: '/settings', label: 'Cài đặt website', perm: 'settings' },
  ] },
].map((g) => ({ ...g, items: g.items.filter((i) => can(i.perm)) })).filter((g) => g.items.length))

// Trang chi tiết sáng menu của module tương ứng
const activeKey = computed(() => ui.navKey || route.path)
const isActive = (to: string, exact?: boolean) => (exact ? activeKey.value === to : activeKey.value.startsWith(to))

async function logout() {
  await api('/logout', { method: 'POST' }).catch(() => {})
  session.me = null
  router.push('/login')
}
</script>

<template>
  <RouterView v-if="route.meta.public" />
  <div v-else-if="session.me" class="adm">
    <aside class="side" :class="{ 'is-open': navOpen }">
      <RouterLink class="side__brand" to="/"><span class="brand__mark" aria-hidden="true" />GenZbaothe.com</RouterLink>
      <nav class="side__nav" aria-label="Menu quản trị">
        <template v-for="g in nav" :key="g.group">
          <p v-if="g.group" class="side__group">{{ g.group }}</p>
          <RouterLink v-for="i in g.items" :key="i.to" :to="i.to" :class="{ 'is-active': isActive(i.to, i.exact) }" :aria-current="isActive(i.to, i.exact) ? 'page' : undefined">
            {{ i.label }}<span v-if="i.count" class="pill" :class="{ 'pill--pink': i.pink }">{{ i.count }}</span>
          </RouterLink>
        </template>
      </nav>
      <div class="side__foot">
        <a :href="demo ? '../index.html' : '/'" :target="demo ? undefined : '_blank'" rel="noopener">Xem website</a>
        <RouterLink to="/profile">{{ session.me.admin.name }} · {{ session.me.role_label }}</RouterLink>
        <button type="button" @click="logout">Đăng xuất</button>
      </div>
    </aside>
    <div v-if="navOpen" class="side__scrim" @click="navOpen = false" />
    <div class="main">
      <button type="button" class="mtop__burger" aria-label="Mở menu" @click="navOpen = true">☰</button>
      <RouterView :key="route.fullPath.split('?')[0]" />
    </div>
  </div>
  <div v-if="toast.text" class="toast" :class="`toast--${toast.kind}`" role="status">{{ toast.text }}</div>
</template>

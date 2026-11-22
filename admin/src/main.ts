import { createApp } from 'vue'
import { createRouter, createWebHashHistory, createWebHistory } from 'vue-router'
import App from './App.vue'
import { api, can, loadSession } from './api'
import './admin.css'

declare const __DEMO__: boolean

const v = (name: string) => () => import(`./views/${name}.vue`)

const router = createRouter({
  history: __DEMO__ ? createWebHashHistory() : createWebHistory('/admin/'),
  routes: [
    { path: '/login', component: v('Login'), meta: { public: true, title: 'Đăng nhập' } },
    { path: '/setup', component: v('Setup'), meta: { public: true, title: 'Tạo tài khoản quản trị' } },
    { path: '/', component: v('Dashboard'), meta: { perm: 'leads.view', title: 'Tổng quan' } },
    { path: '/:mod(loans|cards)/leads', component: v('Leads'), meta: { perm: 'leads.view' } },
    { path: '/leads/:id(\\d+)', component: v('LeadDetail'), meta: { perm: 'leads.view' } },
    { path: '/:mod(loans|cards)/offers', component: v('Offers'), meta: { perm: 'offers' } },
    { path: '/:mod(loans|cards)/offers/new', component: v('OfferForm'), meta: { perm: 'offers' } },
    { path: '/offers/:id(\\d+)', component: v('OfferForm'), meta: { perm: 'offers' } },
    { path: '/customers', component: v('Customers'), meta: { perm: 'leads.view', title: 'Khách hàng' } },
    { path: '/customers/:id(\\d+)', component: v('CustomerDetail'), meta: { perm: 'leads.view' } },
    { path: '/landings', component: v('Landings'), meta: { perm: 'landings', title: 'Landing page' } },
    { path: '/landings/new', component: v('LandingForm'), meta: { perm: 'landings', title: 'Tạo landing' } },
    { path: '/landings/:id(\\d+)', component: v('LandingForm'), meta: { perm: 'landings' } },
    { path: '/users', component: v('Users'), meta: { perm: 'admins', title: 'Tài khoản quản trị' } },
    { path: '/settings', component: v('Settings'), meta: { perm: 'settings', title: 'Cài đặt website' } },
    { path: '/profile', component: v('Profile'), meta: { title: 'Tài khoản của tôi' } },
    { path: '/:rest(.*)*', component: v('NotFound'), meta: { title: 'Không tìm thấy' } },
  ],
})

router.beforeEach(async (to) => {
  if (to.meta.public) {
    if (to.path === '/login') {
      const s = await api<{ needs_setup: boolean }>('/setup').catch(() => ({ needs_setup: false }))
      if (s.needs_setup) return '/setup'
    }
    return true
  }
  const me = await loadSession()
  if (!me) return { path: '/login', query: { next: to.fullPath } }
  if (to.meta.perm && !can(to.meta.perm as string)) return can('leads.view') && to.path !== '/' ? '/' : '/profile'
  return true
})

router.afterEach((to) => {
  if (to.meta.title) document.title = `${to.meta.title} – GenZbaothe.com`
})

createApp(App).use(router).mount('#app')

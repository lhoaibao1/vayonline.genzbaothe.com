<script setup lang="ts">
import { reactive, ref } from 'vue'
import { api, notify, session } from '../api'
import { dt } from '../util'

const items = ref<any[]>([])
const roles = ref<Record<string, string>>({})
const editing = ref<any>(null)
const errors = ref<Record<string, string>>({})
const confirmDel = ref(0)

async function load() {
  const d = await api('/users')
  items.value = d.items
  roles.value = d.roles
}
load()

function edit(u?: any) {
  errors.value = {}
  editing.value = reactive(u ? { ...u, password: '' } : { id: 0, name: '', email: '', password: '', role: 'sales', active: true })
}
async function save() {
  errors.value = {}
  const u = editing.value
  try {
    await api(u.id ? `/users/${u.id}` : '/users', { method: u.id ? 'PUT' : 'POST', body: u })
    notify(`Đã lưu tài khoản ${u.email}.`)
    editing.value = null
    load()
  } catch (e: any) {
    errors.value = e.errors
    notify(e.message, 'err')
  }
}
async function remove(u: any) {
  if (confirmDel.value !== u.id) return (confirmDel.value = u.id)
  try {
    await api(`/users/${u.id}`, { method: 'DELETE' })
    notify(`Đã xoá tài khoản ${u.email}.`)
    editing.value = null
    load()
  } catch (e: any) {
    notify(e.message, 'err')
  }
}
</script>

<template>
  <div>
    <header class="mtop">
      <h1>Tài khoản quản trị</h1>
      <button class="b b--primary" type="button" @click="edit()">Thêm tài khoản</button>
    </header>
    <div class="content">
      <form v-if="editing" class="card stack narrow" @submit.prevent="save">
        <h2 class="card__t" style="margin: 0">{{ editing.id ? 'Sửa tài khoản' : 'Thêm tài khoản' }}</h2>
        <label class="f"><span>Họ tên</span><input v-model="editing.name" required><small v-if="errors.name" class="err">{{ errors.name }}</small></label>
        <label class="f"><span>Email đăng nhập</span><input v-model="editing.email" type="email" required><small v-if="errors.email" class="err">{{ errors.email }}</small></label>
        <label class="f"><span>{{ editing.id ? 'Mật khẩu mới (để trống nếu giữ nguyên)' : 'Mật khẩu (ít nhất 8 ký tự)' }}</span>
          <input v-model="editing.password" type="password" autocomplete="new-password" :required="!editing.id"><small v-if="errors.password" class="err">{{ errors.password }}</small></label>
        <label class="f"><span>Vai trò</span><select v-model="editing.role"><option v-for="(v, k) in roles" :key="k" :value="k">{{ v }}</option></select><small v-if="errors.role" class="err">{{ errors.role }}</small></label>
        <label class="sw"><input v-model="editing.active" type="checkbox"><span>Cho phép đăng nhập</span></label>
        <div class="btns">
          <button class="b b--primary" type="submit">Lưu tài khoản</button>
          <button class="b b--ghost" type="button" @click="editing = null">Huỷ</button>
          <button v-if="editing.id && editing.id !== session.me?.admin.id" class="b b--danger-ghost" type="button" @click="remove(editing)">
            {{ confirmDel === editing.id ? 'Bấm lần nữa để xoá' : 'Xoá tài khoản' }}
          </button>
        </div>
      </form>

      <section class="card card--flush">
        <div class="tscroll">
          <table class="t">
            <thead><tr><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th><th>Đăng nhập gần nhất</th><th /></tr></thead>
            <tbody>
              <tr v-for="u in items" :key="u.id" :class="{ 'is-off': !u.active }">
                <td><b>{{ u.name }}</b></td>
                <td>{{ u.email }}</td>
                <td>{{ roles[u.role] }}</td>
                <td>{{ u.active ? 'Hoạt động' : 'Đã khoá' }}</td>
                <td>{{ u.last_login_at ? dt(u.last_login_at) : 'Chưa đăng nhập' }}</td>
                <td class="r"><button class="linkbtn" type="button" @click="edit(u)">Sửa</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="card">
        <h2 class="card__t">Quyền của từng vai trò</h2>
        <table class="t">
          <thead><tr><th>Vai trò</th><th>Được làm gì</th></tr></thead>
          <tbody>
            <tr><td>Quản trị viên</td><td>Toàn quyền: đăng ký vay và thẻ (xem, sửa, xuất, xoá), gói, landing, cài đặt, tài khoản.</td></tr>
            <tr><td>Biên tập nội dung</td><td>Sửa gói vay, gói thẻ và landing page. Xem danh sách đăng ký, không sửa hay xuất được.</td></tr>
            <tr><td>Tư vấn / chăm sóc khách</td><td>Xem, cập nhật trạng thái, ghi chú và xuất danh sách đăng ký vay và mở thẻ.</td></tr>
          </tbody>
        </table>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import Pager from '../components/Pager.vue'
import { dt } from '../util'

const route = useRoute()
const router = useRouter()
const q = ref('')
const data = ref<any>(null)

watch(() => route.query, async () => {
  q.value = String(route.query.q || '')
  data.value = await api('/customers', { query: { q: q.value, page: route.query.page || 1 } })
}, { immediate: true })

const search = (page = 1) => router.push({ query: { ...(q.value ? { q: q.value } : {}), ...(page > 1 ? { page } : {}) } })
</script>

<template>
  <div>
    <header class="mtop"><h1>Khách hàng</h1></header>
    <div class="content">
      <form class="filters card" @submit.prevent="search()">
        <label class="f f--grow"><span>Tìm khách hàng</span><input v-model="q" placeholder="Tên, SĐT hoặc email"></label>
        <div class="filters__btns">
          <button class="b b--primary" type="submit">Tìm</button>
          <button v-if="route.query.q" class="b b--ghost" type="button" @click="q = ''; search()">Bỏ lọc</button>
        </div>
      </form>
      <section v-if="data" class="card card--flush">
        <div class="bulk"><span><b>{{ data.total.toLocaleString('vi-VN') }}</b> khách hàng (gộp theo số điện thoại)</span></div>
        <p v-if="!data.items.length" class="empty-st">Không tìm thấy khách hàng nào.</p>
        <div v-else class="tscroll">
          <table class="t">
            <thead><tr><th>Khách hàng</th><th>Tỉnh/TP</th><th class="r">Đăng ký vay</th><th class="r">Đăng ký thẻ</th><th>Lần gần nhất</th></tr></thead>
            <tbody>
              <tr v-for="c in data.items" :key="c.id">
                <td><RouterLink :to="`/customers/${c.id}`"><b>{{ c.full_name }}</b></RouterLink><div><a class="tel" :href="`tel:${c.phone}`">{{ c.phone }}</a></div></td>
                <td>{{ c.province }}</td>
                <td class="r">{{ c.loans || '—' }}</td>
                <td class="r">{{ c.cards || '—' }}</td>
                <td class="nowrap">{{ dt(c.last_reg) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <Pager v-if="data" :page="data.page" :total="data.total" :per="data.per" @go="search" />
    </div>
  </div>
</template>

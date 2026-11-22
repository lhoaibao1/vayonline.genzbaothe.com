<script setup lang="ts">
import { ref } from 'vue'
import { api } from '../api'

const items = ref<any[] | null>(null)
api('/landings').then((d) => (items.value = d.items))
const typeText: Record<string, string> = { all: 'Vay và thẻ', loan: 'Chỉ khoản vay', card: 'Chỉ thẻ tín dụng' }
const path = (l: any) => (l.is_default ? '/' : `/lp/${l.slug}`)
</script>

<template>
  <div>
    <header class="mtop">
      <h1>Landing page</h1>
      <RouterLink class="b b--primary" to="/landings/new">Tạo landing</RouterLink>
    </header>
    <div class="content">
      <section v-if="items" class="card card--flush">
        <div class="tscroll">
          <table class="t">
            <thead><tr><th>Landing</th><th>Đường dẫn</th><th>Hiển thị</th><th class="r">Xem 30 ngày</th><th class="r">Đăng ký vay</th><th class="r">Đăng ký thẻ</th><th class="r">Tỉ lệ</th><th /></tr></thead>
            <tbody>
              <tr v-for="l in items" :key="l.id" :class="{ 'is-off': !l.active }">
                <td>
                  <RouterLink :to="`/landings/${l.id}`"><b>{{ l.name }}</b></RouterLink>
                  <span v-if="l.is_default" class="chip chip--loan">Trang chủ</span>
                  <span v-if="!l.active" class="chip">Đang tắt</span>
                  <div class="muted">{{ l.headline.replace(/\*/g, '') }}</div>
                </td>
                <td><code>{{ path(l) }}</code></td>
                <td>{{ typeText[l.offer_type] }}{{ l.offer_ids.length ? ` · ${l.offer_ids.length} gói chọn riêng` : '' }}</td>
                <td class="r">{{ l.views30.toLocaleString('vi-VN') }}</td>
                <td class="r"><RouterLink :to="`/loans/leads?landing=${l.id}`">{{ l.loans30 }}</RouterLink></td>
                <td class="r"><RouterLink :to="`/cards/leads?landing=${l.id}`">{{ l.cards30 }}</RouterLink></td>
                <td class="r">{{ l.views30 ? (((l.loans30 + l.cards30) / l.views30) * 100).toFixed(1) + '%' : '—' }}</td>
                <td class="nowrap r"><a :href="path(l)" target="_blank" rel="noopener">Xem</a> · <RouterLink :to="`/landings/${l.id}`">Sửa</RouterLink></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <p class="muted small">
        Gắn UTM vào link quảng cáo để biết khách đến từ đâu, ví dụ <code>/lp/mo-the-tin-dung?utm_source=facebook&amp;utm_campaign=the-thang-11</code>.
        Trang riêng của từng module: <code>/khoan-vay</code> và <code>/the-tin-dung</code>.
      </p>
    </div>
  </div>
</template>

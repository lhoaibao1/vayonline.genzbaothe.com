<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, notify } from '../api'
import { initials, modText, modType, short, type Mod } from '../util'

const route = useRoute()
const mod = computed(() => route.params.mod as Mod)
const T = computed(() => modText[mod.value])
const isLoan = computed(() => mod.value === 'loans')
const items = ref<any[] | null>(null)

async function load() {
  items.value = (await api('/offers', { query: { type: modType(mod.value) } })).items
  document.title = `${T.value.offers} – GenZbaothe.com`
}
load()

async function toggle(o: any) {
  try {
    await api(`/offers/${o.id}`, { method: 'PUT', body: { ...o, active: !o.active } })
    o.active = !o.active
    notify(o.active ? `Đã hiện “${o.name}” trên website.` : `Đã ẩn “${o.name}” khỏi website.`)
  } catch (e: any) {
    notify(e.message, 'err')
  }
}
</script>

<template>
  <div>
    <header class="mtop">
      <h1>{{ T.offers }}</h1>
      <RouterLink class="b b--ghost" :to="`/${mod}/leads`">Xem {{ T.lead }}</RouterLink>
      <RouterLink class="b b--primary" :to="`/${mod}/offers/new`">Thêm {{ T.one }}</RouterLink>
    </header>
    <div class="content">
      <section v-if="items" class="card card--flush">
        <p v-if="!items.length" class="empty-st">Chưa có {{ T.one }} nào. <RouterLink :to="`/${mod}/offers/new`">Thêm {{ T.one }} đầu tiên</RouterLink></p>
        <div v-else class="tscroll">
          <table class="t">
            <thead>
              <tr><th>Gói</th><th>Hạn mức</th><th>{{ isLoan ? 'Lãi suất' : 'Ưu đãi lãi' }}</th><th>{{ isLoan ? 'Kỳ hạn' : 'Phí thường niên' }}</th>
                <th class="r">Đăng ký 30 ngày</th><th class="r">Tổng</th><th class="r">Thứ tự</th><th>Hiển thị</th><th /></tr>
            </thead>
            <tbody>
              <tr v-for="o in items" :key="o.id" :class="{ 'is-off': !o.active }">
                <td>
                  <div class="ofr">
                    <img v-if="o.logo" :src="o.logo" alt="" class="ofr__logo">
                    <span v-else class="ofr__logo">{{ initials(o.partner || o.name) }}</span>
                    <div>
                      <RouterLink :to="`/offers/${o.id}`"><b>{{ o.name }}</b></RouterLink>
                      <div class="muted">{{ o.partner }}<span v-if="!o.affiliate_url"> · <span class="warn">chưa có link đối tác</span></span></div>
                    </div>
                  </div>
                </td>
                <td class="nowrap">{{ o.amount_max ? `${short(o.amount_min)} – ${short(o.amount_max)}` : '—' }}</td>
                <td>{{ o.rate_text || '—' }}</td>
                <td class="nowrap">{{ isLoan ? (o.term_max ? `${o.term_min} – ${o.term_max} tháng` : '—') : o.annual_fee || '—' }}</td>
                <td class="r"><RouterLink :to="`/${mod}/leads?offer=${o.id}`">{{ o.regs30 }}</RouterLink></td>
                <td class="r">{{ o.regs }}</td>
                <td class="r">{{ o.sort_order }}</td>
                <td>
                  <button class="toggle" :class="{ 'is-on': o.active }" type="button" role="switch" :aria-checked="o.active"
                    :aria-label="o.active ? 'Đang hiển thị – bấm để ẩn' : 'Đang ẩn – bấm để hiển thị'" @click="toggle(o)"><span /></button>
                </td>
                <td class="nowrap r"><a :href="`/uu-dai/${o.slug}`" target="_blank" rel="noopener">Xem</a> · <RouterLink :to="`/offers/${o.id}`">Sửa</RouterLink></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <p class="muted small">Thứ tự nhỏ hiển thị trước. Gói bị ẩn không xuất hiện trên landing, đăng ký cũ vẫn được giữ.</p>
    </div>
  </div>
</template>

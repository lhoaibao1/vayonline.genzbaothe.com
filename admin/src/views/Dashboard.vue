<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api } from '../api'
import { modText, type Mod } from '../util'

interface ModStat { today: number; regs: number; new: number; clicked: number; approved: number; offers: { id: number; name: string; regs: number; clicked: number; approved: number }[] }
interface Dash {
  range: number
  modules: { loan: ModStat; card: ModStat }
  days: { day: string; loan: number; card: number }[]
  views: number
  landings: { id: number; name: string; views: number; loan: number; card: number }[]
  sources: { source: string; count: number }[]
}

const range = ref(30)
const d = ref<Dash | null>(null)
const error = ref('')
watch(range, async () => {
  try { d.value = await api<Dash>('/dashboard', { query: { range: range.value } }) } catch (e: any) { error.value = e.message }
}, { immediate: true })

const mods = computed(() => d.value ? ([['loans', d.value.modules.loan], ['cards', d.value.modules.card]] as [Mod, ModStat][]) : [])
const maxDay = computed(() => Math.max(1, ...(d.value?.days.map((x) => x.loan + x.card) ?? [0])))
const maxSrc = computed(() => Math.max(1, ...(d.value?.sources.map((s) => s.count) ?? [0])))
const totalRegs = computed(() => (d.value ? d.value.modules.loan.regs + d.value.modules.card.regs : 0))
const pct = (a: number, b: number) => (b ? Math.round((a / b) * 100) : 0)
const ddmm = (s: string) => s.slice(8, 10) + '/' + s.slice(5, 7)
</script>

<template>
  <div>
    <header class="mtop">
      <h1>Tổng quan</h1>
      <div class="seg" role="group" aria-label="Khoảng thời gian">
        <button v-for="r in [7, 30, 90]" :key="r" type="button" :class="{ 'is-on': range === r }" @click="range = r">{{ r }} ngày</button>
      </div>
    </header>
    <p v-if="error" class="flash flash--error">{{ error }}</p>
    <div v-if="d" class="content">
      <div class="mod-grid">
        <section v-for="[mod, m] in mods" :key="mod" class="card mod" :class="`mod--${mod}`">
          <div class="card__head">
            <h2 class="mod__t">{{ modText[mod].name }}</h2>
            <RouterLink :to="`/${mod}/leads`">Xem {{ modText[mod].lead }}</RouterLink>
          </div>
          <div class="mod__kpis">
            <RouterLink class="mod__kpi mod__kpi--hot" :to="`/${mod}/leads?status=new`"><span>Chờ xử lý</span><strong>{{ m.new }}</strong></RouterLink>
            <div class="mod__kpi"><span>Hôm nay</span><strong>{{ m.today }}</strong></div>
            <div class="mod__kpi"><span>{{ d.range }} ngày</span><strong>{{ m.regs }}</strong></div>
            <div class="mod__kpi"><span>Sang đối tác</span><strong>{{ pct(m.clicked, m.regs) }}%</strong></div>
            <div class="mod__kpi"><span>Được duyệt</span><strong>{{ m.approved }}</strong></div>
          </div>
          <table class="t">
            <thead><tr><th>{{ modText[mod].offers }}</th><th class="r">Đăng ký</th><th class="r">Sang đối tác</th><th class="r">Duyệt</th></tr></thead>
            <tbody>
              <tr v-for="o in m.offers" :key="o.id">
                <td><RouterLink :to="`/${mod}/leads?offer=${o.id}`">{{ o.name }}</RouterLink></td>
                <td class="r">{{ o.regs }}</td><td class="r">{{ o.clicked }}</td><td class="r">{{ o.approved }}</td>
              </tr>
              <tr v-if="!m.offers.length"><td colspan="4" class="muted">Chưa có gói nào. <RouterLink :to="`/${mod}/offers/new`">Thêm {{ modText[mod].one }}</RouterLink></td></tr>
            </tbody>
          </table>
        </section>
      </div>

      <section class="card">
        <div class="card__head">
          <h2 class="card__t">Đăng ký theo ngày</h2>
          <p class="legend"><span class="lg lg--loan" />Khoản vay <span class="lg lg--card" />Thẻ tín dụng</p>
        </div>
        <div class="chart" role="img" :aria-label="`Biểu đồ đăng ký vay và mở thẻ trong ${d.range} ngày`">
          <div v-for="x in d.days" :key="x.day" class="chart__col" :title="`${ddmm(x.day)}: ${x.loan} đăng ký vay, ${x.card} đăng ký thẻ`">
            <b v-if="x.loan + x.card">{{ x.loan + x.card }}</b>
            <span class="chart__stack" :style="{ height: ((x.loan + x.card) / maxDay) * 100 + '%' }">
              <span class="chart__bar chart__bar--card" :style="{ flex: x.card }" />
              <span class="chart__bar chart__bar--loan" :style="{ flex: x.loan }" />
            </span>
          </div>
        </div>
        <div class="chart__axis"><span>{{ ddmm(d.days[0].day) }}</span><span>{{ ddmm(d.days[d.days.length - 1].day) }}</span></div>
      </section>

      <div class="grid2">
        <section class="card">
          <h2 class="card__t">Theo landing page</h2>
          <p class="muted small" style="margin: -8px 0 8px">Tổng {{ d.views.toLocaleString('vi-VN') }} lượt xem, tỉ lệ đăng ký {{ d.views ? ((totalRegs / d.views) * 100).toFixed(1) : 0 }}%</p>
          <table class="t">
            <thead><tr><th>Landing</th><th class="r">Lượt xem</th><th class="r">Vay</th><th class="r">Thẻ</th><th class="r">Tỉ lệ</th></tr></thead>
            <tbody>
              <tr v-for="l in d.landings" :key="l.id">
                <td><RouterLink :to="`/landings/${l.id}`">{{ l.name }}</RouterLink></td>
                <td class="r">{{ l.views.toLocaleString('vi-VN') }}</td>
                <td class="r"><RouterLink :to="`/loans/leads?landing=${l.id}`">{{ l.loan }}</RouterLink></td>
                <td class="r"><RouterLink :to="`/cards/leads?landing=${l.id}`">{{ l.card }}</RouterLink></td>
                <td class="r">{{ l.views ? (((l.loan + l.card) / l.views) * 100).toFixed(1) + '%' : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </section>
        <section class="card">
          <h2 class="card__t">Theo nguồn (utm_source)</h2>
          <p v-if="!d.sources.length" class="muted">Chưa có dữ liệu trong khoảng thời gian này.</p>
          <ul class="bars">
            <li v-for="s in d.sources" :key="s.source"><span>{{ s.source }}</span><i :style="{ '--w': (s.count / maxSrc) * 100 + '%' }" /><b>{{ s.count }}</b></li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>

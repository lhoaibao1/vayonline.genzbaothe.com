<script setup lang="ts">
import type { Offer } from '~/composables/api'

const route = useRoute()
const slug = String(route.params.slug)
const { data, error } = await useApi<{ offer: Offer; related: Offer[] }>(`offer-${slug}`, `/api/public/offers/${encodeURIComponent(slug)}`)
if (error.value || !data.value) throw createError({ statusCode: 404, message: 'Gói này không còn nhận đăng ký', fatal: true })

const { data: site } = await useSite()
const reg = useRegister()
const o = computed(() => data.value!.offer)
const isLoan = computed(() => o.value.type === 'loan')
const who = computed(() => o.value.partner || o.value.name)

useSeoMeta({
  title: () => `${o.value.name} – ${site.value?.site_name}`,
  description: () => o.value.description || `${o.value.name}: ${o.value.rate_text}, hạn mức đến ${short(o.value.amount_max)}.`,
})
</script>

<template>
  <section class="detail">
    <div class="wrap">
      <nav class="crumbs" aria-label="Vị trí">
        <NuxtLink to="/">Trang chủ</NuxtLink> / <NuxtLink :to="isLoan ? '/khoan-vay' : '/the-tin-dung'">{{ isLoan ? 'Khoản vay' : 'Thẻ tín dụng' }}</NuxtLink>
      </nav>
      <div class="detail__grid">
        <div>
          <div class="detail__head">
            <img v-if="o.logo" class="logo logo--lg" :src="o.logo" :alt="who" width="80" height="80">
            <span v-else class="logo logo--lg" aria-hidden="true">{{ initials(who) }}</span>
            <div>
              <h1>{{ o.name }}</h1>
              <p class="offer__partner">{{ o.partner }} <span v-if="o.badge" class="tag">{{ o.badge }}</span></p>
            </div>
          </div>
          <p v-if="o.description" class="detail__lead">{{ o.description }}</p>
          <template v-if="lines(o.highlights).length">
            <h2>Điểm nổi bật</h2>
            <ul class="ticks"><li v-for="l in lines(o.highlights)" :key="l">{{ l }}</li></ul>
          </template>
          <template v-if="lines(o.requirements).length">
            <h2>Điều kiện đăng ký</h2>
            <ul class="ticks ticks--plain"><li v-for="l in lines(o.requirements)" :key="l">{{ l }}</li></ul>
          </template>
        </div>

        <aside class="detail__card">
          <dl class="detail__facts">
            <div><dt>{{ isLoan ? 'Hạn mức' : 'Hạn mức thẻ' }}</dt><dd>{{ o.amount_max ? `${short(o.amount_min)} – ${short(o.amount_max)}` : 'Theo hồ sơ' }}</dd></div>
            <div><dt>{{ isLoan ? 'Lãi suất' : 'Ưu đãi lãi' }}</dt><dd>{{ o.rate_text || '—' }}</dd></div>
            <div v-if="isLoan"><dt>Kỳ hạn</dt><dd>{{ o.term_max ? `${o.term_min} – ${o.term_max} tháng` : '—' }}</dd></div>
            <div v-if="!isLoan && o.annual_fee"><dt>Phí thường niên</dt><dd>{{ o.annual_fee }}</dd></div>
            <div v-if="o.age_text"><dt>Độ tuổi</dt><dd>{{ o.age_text }}</dd></div>
            <div v-if="o.approval_text"><dt>Thời gian duyệt</dt><dd>{{ o.approval_text }}</dd></div>
          </dl>
          <button type="button" class="btn btn--gold btn--block btn--lg" @click="reg.offer = o">{{ isLoan ? 'Đăng ký vay gói này' : 'Mở thẻ này' }}</button>
          <p class="regform__note">Miễn phí đăng ký. Kết quả duyệt do đối tác quyết định.</p>
        </aside>
      </div>

      <template v-if="data!.related.length">
        <h2 class="detail__more">{{ isLoan ? 'Gói vay tương tự' : 'Thẻ tương tự' }}</h2>
        <div class="offer-list">
          <OfferCard v-for="r in data!.related" :key="r.id" :offer="r" />
        </div>
      </template>
    </div>
  </section>
</template>

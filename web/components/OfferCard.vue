<script setup lang="ts">
import type { Offer } from '~/composables/api'

const props = defineProps<{ offer: Offer; fit?: 'fit' | 'out' | null; fitText?: string }>()
const reg = useRegister()
const isLoan = computed(() => props.offer.type === 'loan')
const who = computed(() => props.offer.partner || props.offer.name)

function open() {
  reg.value.offer = props.offer
}
</script>

<template>
  <article class="offer" :data-type="offer.type" :class="{ 'is-fit': fit === 'fit', 'is-out': fit === 'out' }">
    <div class="offer__top">
      <img v-if="offer.logo" class="logo" :src="offer.logo" :alt="who" width="56" height="56" loading="lazy">
      <span v-else class="logo" aria-hidden="true">{{ initials(who) }}</span>
      <div>
        <h3 class="offer__name"><NuxtLink :to="`/uu-dai/${offer.slug}`">{{ offer.name }}</NuxtLink></h3>
        <p class="offer__partner">{{ offer.partner }} <span v-if="offer.badge" class="tag">{{ offer.badge }}</span></p>
      </div>
    </div>

    <div class="offer__body">
      <p class="offer__big">
        <small>{{ isLoan ? 'Vay tối đa' : 'Hạn mức thẻ đến' }}</small>
        <strong>{{ offer.amount_max ? short(offer.amount_max) : 'Theo hồ sơ' }}</strong>
      </p>
      <dl class="offer__facts">
        <div><dt>{{ isLoan ? 'Lãi suất' : 'Ưu đãi lãi' }}</dt><dd>{{ offer.rate_text || '—' }}</dd></div>
        <div><dt>Duyệt trong</dt><dd>{{ offer.approval_text || '—' }}</dd></div>
        <template v-if="isLoan">
          <div><dt>Kỳ hạn</dt><dd>{{ offer.term_max ? `${offer.term_min} – ${offer.term_max} tháng` : '—' }}</dd></div>
          <div><dt>Từ</dt><dd>{{ short(offer.amount_min) }}</dd></div>
        </template>
        <template v-else>
          <div><dt>Phí năm</dt><dd>{{ offer.annual_fee || '—' }}</dd></div>
          <div><dt>Độ tuổi</dt><dd>{{ offer.age_text || '—' }}</dd></div>
        </template>
      </dl>
    </div>
    <p v-if="fitText" class="offer__fit">{{ fitText }}</p>

    <div class="offer__act">
      <button type="button" class="btn btn--gold btn--block" @click="open">{{ isLoan ? 'Đăng ký vay' : 'Mở thẻ ngay' }}</button>
      <NuxtLink class="offer__more" :to="`/uu-dai/${offer.slug}`">Xem điều kiện</NuxtLink>
    </div>
  </article>
</template>

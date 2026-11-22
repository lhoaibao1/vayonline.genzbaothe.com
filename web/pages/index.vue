<script setup lang="ts">
import type { LandingData } from '~/composables/api'
const { data, error } = await useApi<LandingData>('landing-home', '/api/public/landing')
if (error.value || !data.value) throw createError({ statusCode: 404, message: 'Không tìm thấy trang này', fatal: true })
</script>

<template>
  <LandingView v-if="data" :data="data" />
</template>

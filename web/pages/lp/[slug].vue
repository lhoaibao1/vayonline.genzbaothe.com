<script setup lang="ts">
import type { LandingData } from '~/composables/api'
const route = useRoute()
const slug = String(route.params.slug)
const { data, error } = await useApi<LandingData>(`lp-${slug}`, `/api/public/landing?slug=${encodeURIComponent(slug)}`)
if (error.value || !data.value) throw createError({ statusCode: 404, message: 'Không tìm thấy trang này', fatal: true })
</script>

<template>
  <LandingView v-if="data" :data="data" />
</template>

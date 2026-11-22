<script setup lang="ts">
// Trang pháp lý: điều khoản sử dụng, chính sách bảo mật, thông báo xử lý dữ liệu cá nhân
const route = useRoute()
const slug = String(route.params.slug)
const { data, error } = await useApi<{ slug: string; title: string; content: string; version: number; updated_at: string }>(
  `page-${slug}`, `/api/public/pages/${encodeURIComponent(slug)}`,
)
if (error.value || !data.value) throw createError({ statusCode: 404, message: 'Không tìm thấy trang này', fatal: true })

const { data: site } = await useSite()
const html = computed(() => markdown(data.value!.content))
useSeoMeta({ title: () => `${data.value!.title} – ${site.value?.site_name}`, robots: 'index, follow' })
</script>

<template>
  <section class="legal">
    <div class="wrap legal__in">
      <nav class="crumbs" aria-label="Vị trí"><NuxtLink to="/">Trang chủ</NuxtLink> / {{ data!.title }}</nav>
      <h1>{{ data!.title }}</h1>
      <nav class="legal__tabs" aria-label="Các chính sách">
        <NuxtLink v-for="p in site?.pages" :key="p.slug" :to="`/${p.slug}`" :aria-current="p.slug === slug ? 'page' : undefined">{{ p.title }}</NuxtLink>
        <NuxtLink to="/yeu-cau-du-lieu">Yêu cầu về dữ liệu cá nhân</NuxtLink>
      </nav>
      <article class="legal__body" v-html="html" />
    </div>
  </section>
</template>

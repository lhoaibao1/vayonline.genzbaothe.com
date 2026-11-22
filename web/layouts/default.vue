<script setup lang="ts">
const { data: site } = await useSite()
const tel = computed(() => (site.value?.hotline || '').replace(/\D/g, ''))
const zalo = computed(() => (site.value?.zalo || '').replace(/\D/g, ''))
</script>

<template>
  <div>
    <header class="topbar">
      <div class="wrap topbar__in">
        <NuxtLink class="brand" to="/" :aria-label="`${site?.site_name} – trang chủ`">
          <span class="brand__mark" aria-hidden="true" />
          <span class="brand__name">{{ site?.site_name }}</span>
        </NuxtLink>
        <nav class="topnav" aria-label="Điều hướng chính">
          <NuxtLink to="/khoan-vay">Khoản vay</NuxtLink>
          <NuxtLink to="/the-tin-dung">Thẻ tín dụng</NuxtLink>
          <NuxtLink to="/#cach-dang-ky">Cách đăng ký</NuxtLink>
          <NuxtLink to="/#hoi-dap">Hỏi đáp</NuxtLink>
        </nav>
        <a v-if="tel" class="topbar__call" :href="`tel:${tel}`">Gọi {{ site?.hotline }}</a>
      </div>
    </header>

    <main id="noi-dung"><slot /></main>

    <footer class="footer">
      <div class="wrap footer__grid">
        <div>
          <NuxtLink class="brand brand--light" to="/"><span class="brand__mark" aria-hidden="true" /><span class="brand__name">{{ site?.site_name }}</span></NuxtLink>
          <p class="footer__about">{{ site?.company_info }}</p>
        </div>
        <div v-if="tel || zalo || site?.contact_email" class="footer__contact">
          <h2>Liên hệ</h2>
          <ul>
            <li v-if="tel">Hotline: <a :href="`tel:${tel}`">{{ site?.hotline }}</a></li>
            <li v-if="zalo">Zalo: <a :href="`https://zalo.me/${zalo}`" target="_blank" rel="noopener">{{ site?.zalo }}</a></li>
            <li v-if="site?.contact_email">Email: <a :href="`mailto:${site.contact_email}`">{{ site.contact_email }}</a></li>
          </ul>
        </div>
      </div>
      <nav class="wrap footer__links" aria-label="Chính sách">
        <NuxtLink v-for="p in site?.pages" :key="p.slug" :to="`/${p.slug}`">{{ p.title }}</NuxtLink>
        <NuxtLink to="/yeu-cau-du-lieu">Yêu cầu về dữ liệu cá nhân</NuxtLink>
      </nav>
      <div class="wrap footer__legal">
        <p>{{ site?.disclaimer }}</p>
        <p v-if="site?.company_name">
          {{ site.company_name }}<template v-if="site.tax_code"> · Mã số doanh nghiệp {{ site.tax_code }}</template><template v-if="site.company_address"> · {{ site.company_address }}</template>
        </p>
        <p>© {{ new Date().getFullYear() }} {{ site?.site_name }}</p>
      </div>
    </footer>

    <RegisterSheet />
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ error: { statusCode: number; message?: string } }>()
useHead({ title: props.error.statusCode === 404 ? 'Không tìm thấy trang' : 'Có lỗi xảy ra' })
</script>

<template>
  <main class="wrap notfound">
    <h1>{{ error.statusCode === 404 ? 'Không tìm thấy trang này' : 'Hệ thống đang gặp sự cố' }}</h1>
    <p>{{ error.statusCode === 404 ? 'Đường dẫn có thể đã thay đổi hoặc gói đã ngừng nhận đăng ký.' : 'Hãy thử tải lại trang sau ít phút.' }}</p>
    <button class="btn btn--gold" @click="clearError({ redirect: '/' })">Về trang chủ</button>
  </main>
</template>

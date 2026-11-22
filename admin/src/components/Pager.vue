<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ page: number; total: number; per: number }>()
const emit = defineEmits<{ go: [page: number] }>()
const pages = computed(() => Math.ceil(props.total / props.per))
const list = computed(() => {
  const out: number[] = []
  for (let i = Math.max(1, props.page - 3); i <= Math.min(pages.value, props.page + 3); i++) out.push(i)
  return out
})
</script>

<template>
  <nav v-if="pages > 1" class="pager" aria-label="Phân trang">
    <button v-for="i in list" :key="i" type="button" :class="{ 'is-on': i === page }" :aria-current="i === page ? 'page' : undefined" @click="emit('go', i)">{{ i }}</button>
    <span class="muted">Trang {{ page }}/{{ pages }}</span>
  </nav>
</template>

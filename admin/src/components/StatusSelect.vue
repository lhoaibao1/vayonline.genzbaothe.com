<script setup lang="ts">
import { ref } from 'vue'
import { api, can, label, notify, session } from '../api'

// Đổi trạng thái đăng ký ngay trên bảng
const props = defineProps<{ id: number; status: string }>()
const emit = defineEmits<{ changed: [status: string] }>()
const value = ref(props.status)
const busy = ref(false)

async function change() {
  busy.value = true
  try {
    await api(`/leads/${props.id}`, { method: 'PATCH', body: { status: value.value } })
    emit('changed', value.value)
    notify(`R${props.id}: ${label('status', value.value)}`)
  } catch (e: any) {
    value.value = props.status
    notify(e.message, 'err')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <select v-if="can('leads.edit')" v-model="value" class="st-select" :class="`st--${value}`" :disabled="busy" :aria-label="`Trạng thái R${id}`" @change="change">
    <option v-for="o in session.options?.status" :key="o.value" :value="o.value">{{ o.label }}</option>
  </select>
  <span v-else class="st" :class="`st--${status}`">{{ label('status', status) }}</span>
</template>

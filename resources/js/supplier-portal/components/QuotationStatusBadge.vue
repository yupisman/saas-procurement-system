<template>
  <div class="flex flex-col items-end gap-1 shrink-0">
    <span class="badge badge-sm" :class="badgeClass">{{ label }}</span>
    <span v-if="isBest" class="badge badge-warning badge-xs">⭐ Terbaik</span>
  </div>
</template>
<script setup>
import { computed } from 'vue'
const props = defineProps({ status: String, isBest: Boolean })
const map = {
  submitted: { class: 'badge-info',    label: 'Terkirim' },
  review:    { class: 'badge-warning', label: 'Direview' },
  selected:  { class: 'badge-success', label: 'Dipilih' },
  rejected:  { class: 'badge-error',   label: 'Ditolak' },
  revised:   { class: 'badge-ghost',   label: 'Revisi' },
}
const badgeClass = computed(() => map[props.status]?.class ?? 'badge-ghost')
const label      = computed(() => map[props.status]?.label ?? props.status)
</script>

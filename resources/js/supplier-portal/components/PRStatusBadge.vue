<template>
  <span class="badge badge-sm shrink-0" :class="badgeClass">{{ label }}</span>
</template>
<script setup>
import { computed } from 'vue'
const props = defineProps({ status: String })
const map = {
  draft:          { class: 'badge-ghost',   label: 'Draft' },
  didistribusi:   { class: 'badge-warning', label: 'Didistribusi' },
  penawaran:      { class: 'badge-info',    label: 'Penawaran' },
  evaluasi:       { class: 'badge-primary', label: 'Evaluasi' },
  disetujui:      { class: 'badge-success', label: 'Disetujui' },
  po_diterbitkan: { class: 'badge-success', label: 'PO Terbit' },
  pengiriman:     { class: 'badge-accent',  label: 'Pengiriman' },
  selesai:        { class: 'badge-success', label: 'Selesai' },
  dibatalkan:     { class: 'badge-error',   label: 'Batal' },
}
const badgeClass = computed(() => map[props.status]?.class ?? 'badge-ghost')
const label      = computed(() => map[props.status]?.label ?? props.status)
</script>

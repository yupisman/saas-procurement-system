<template>
  <div v-if="modelValue" class="fixed inset-0 bg-black/40 z-40" @click="$emit('update:modelValue', false)"></div>
  <transition name="slide-right">
    <div v-if="modelValue" class="fixed right-0 top-0 bottom-0 w-80 max-w-full bg-base-100 shadow-2xl z-50 flex flex-col">
      <div class="flex items-center justify-between px-4 py-4 border-b border-base-200 bg-emerald-700 text-white">
        <h2 class="font-bold">Notifikasi</h2>
        <div class="flex items-center gap-2">
          <button v-if="unreadCount > 0" @click="markAll" class="btn btn-ghost btn-xs text-white text-xs">Tandai semua dibaca</button>
          <button @click="$emit('update:modelValue', false)" class="btn btn-ghost btn-circle btn-sm text-white">✕</button>
        </div>
      </div>
      <div class="flex-1 overflow-y-auto">
        <div v-if="loading" class="p-4 space-y-3">
          <div v-for="i in 3" :key="i" class="h-16 bg-base-200 rounded animate-pulse"></div>
        </div>
        <div v-else-if="notifications.length === 0" class="text-center py-16 text-base-content/40">
          <p class="text-3xl mb-2">🔔</p>
          <p class="text-sm">Tidak ada notifikasi.</p>
        </div>
        <div v-else>
          <div v-for="notif in notifications" :key="notif.id" @click="handleNotifClick(notif)" class="px-4 py-3 border-b border-base-200 cursor-pointer transition-colors" :class="notif.is_read ? 'bg-base-100' : 'bg-emerald-50'">
            <div class="flex gap-3 items-start">
              <span class="text-xl mt-0.5">{{ notifIcon(notif.type) }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold leading-snug" :class="notif.is_read ? '' : 'text-emerald-800'">{{ notif.title }}</p>
                <p class="text-xs text-base-content/60 mt-0.5 leading-snug line-clamp-2">{{ notif.message }}</p>
                <p class="text-xs text-base-content/40 mt-1">{{ timeAgo(notif.created_at) }}</p>
              </div>
              <div v-if="!notif.is_read" class="w-2 h-2 rounded-full bg-emerald-500 mt-2 shrink-0"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>
<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useProcurementStore } from '../stores/procurement'
import axios from 'axios'
defineProps({ modelValue: Boolean })
const emit = defineEmits(['update:modelValue'])
const procurement = useProcurementStore()
const { notifications, unreadCount, loading } = storeToRefs(procurement)
const router = useRouter()
async function markAll() { await procurement.markAllRead() }
async function handleNotifClick(notif) {
  if (!notif.is_read) {
    await axios.patch(`/notifications/${notif.id}/read`)
    notif.is_read = true
    procurement.unreadCount = Math.max(0, unreadCount.value - 1)
  }
  if (notif.type === 'pr_distributed' && notif.data?.pr_id) {
    emit('update:modelValue', false)
    router.push(`/pr/${notif.data.pr_id}`)
  }
}
function notifIcon(type) {
  const map = { pr_distributed: '📋', quotation_approved: '✅', po_issued: '📦', invoice_verified: '🧾' }
  return map[type] ?? '🔔'
}
function timeAgo(d) {
  const diff = Date.now() - new Date(d).getTime()
  const m = Math.floor(diff / 60000)
  if (m < 1) return 'Baru saja'
  if (m < 60) return `${m} menit lalu`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h} jam lalu`
  return `${Math.floor(h/24)} hari lalu`
}
</script>
<style scoped>
.slide-right-enter-active, .slide-right-leave-active { transition: transform 0.25s ease; }
.slide-right-enter-from, .slide-right-leave-to { transform: translateX(100%); }
</style>

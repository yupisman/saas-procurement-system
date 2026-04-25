<template>
<!--
  FILE: resources/js/supplier-portal/views/DashboardView.vue
  PURPOSE: Dashboard supplier - ringkasan statistik, notifikasi, shortcut
-->
  <div class="min-h-screen bg-base-200">

    <!-- Header -->
    <header class="bg-emerald-700 text-white px-4 pt-10 pb-16">
      <div class="max-w-lg mx-auto">
        <p class="text-emerald-200 text-sm">Selamat datang,</p>
        <h1 class="text-xl font-bold">{{ auth.user?.supplier?.company_name ?? auth.user?.name }}</h1>
        <p class="text-emerald-300 text-xs mt-1">{{ new Date().toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' }) }}</p>
      </div>
    </header>

    <div class="max-w-lg mx-auto px-4 -mt-8 pb-6 space-y-5">

      <!-- Stats Cards -->
      <div v-if="stats" class="grid grid-cols-2 gap-3">
        <StatCard label="PR Diterima"     :value="stats.pr_diterima"       icon="📋" color="bg-white" />
        <StatCard label="Penawaran Dikirim" :value="stats.penawaran_dikirim" icon="📤" color="bg-white" />
        <StatCard label="Penawaran Menang" :value="stats.penawaran_menang"  icon="🏆" color="bg-white" />
        <StatCard label="Total PO"         :value="stats.total_po"          icon="📦" color="bg-white" />
      </div>
      <div v-else-if="loading" class="grid grid-cols-2 gap-3">
        <div v-for="i in 4" :key="i" class="card bg-white h-24 animate-pulse"></div>
      </div>

      <!-- Win Rate & Rating -->
      <div v-if="stats" class="card bg-white shadow-sm">
        <div class="card-body py-4">
          <div class="flex justify-between items-center">
            <div class="text-center flex-1">
              <p class="text-2xl font-bold text-emerald-600">{{ Number(stats.win_rate).toFixed(1) }}%</p>
              <p class="text-xs text-base-content/60">Win Rate</p>
            </div>
            <div class="divider divider-horizontal"></div>
            <div class="text-center flex-1">
              <p class="text-2xl font-bold text-amber-500">{{ Number(stats.rating).toFixed(1) }} ⭐</p>
              <p class="text-xs text-base-content/60">Rating</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="grid grid-cols-2 gap-3">
        <router-link to="/pr" class="btn btn-outline btn-primary h-auto py-4 flex-col gap-1">
          <span class="text-2xl">📄</span>
          <span class="text-xs font-medium">Lihat Daftar PR</span>
        </router-link>
        <router-link to="/penawaran-saya" class="btn btn-outline btn-accent h-auto py-4 flex-col gap-1">
          <span class="text-2xl">📋</span>
          <span class="text-xs font-medium">Penawaran Saya</span>
        </router-link>
        <router-link to="/pengiriman" class="btn btn-outline btn-success h-auto py-4 flex-col gap-1">
          <span class="text-2xl">🚚</span>
          <span class="text-xs font-medium">Pengiriman</span>
        </router-link>
        <router-link to="/invoice" class="btn btn-outline btn-warning h-auto py-4 flex-col gap-1">
          <span class="text-2xl">🧾</span>
          <span class="text-xs font-medium">INVOICE</span>
        </router-link>
      </div>

      <!-- Logout -->
      <button @click="handleLogout" class="btn btn-ghost btn-block text-error text-sm">
        Keluar dari Akun
      </button>

    </div>
  </div>
</template>

<script setup>
import { onMounted, computed } from 'vue'
import { useRouter }           from 'vue-router'
import { storeToRefs }         from 'pinia'
import { useAuthStore }        from '../stores/auth'
import { useProcurementStore } from '../stores/procurement'
import StatCard                from '../components/StatCard.vue'

const auth        = useAuthStore()
const procurement = useProcurementStore()
const router      = useRouter()
const { loading } = storeToRefs(procurement)
const stats       = computed(() => procurement.dashboardStats)

onMounted(() => procurement.fetchDashboard())

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'Login' })
}
</script>

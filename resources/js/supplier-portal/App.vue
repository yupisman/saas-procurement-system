<template>
<!--
  FILE: resources/js/supplier-portal/App.vue
  PURPOSE: Root component - layout dengan bottom nav (mobile-first)
-->
  <div class="min-h-screen bg-base-200 font-sans">

    <!-- Main Content -->
    <div :class="isLoggedIn ? 'pb-20' : ''">
      <router-view v-slot="{ Component, route }">
        <transition name="fade" mode="out-in">
          <component :is="Component" :key="route.path" />
        </transition>
      </router-view>
    </div>

    <!-- Bottom Navigation Bar (mobile-first, DaisyUI) -->
    <nav v-if="isLoggedIn"
         class="btm-nav btm-nav-sm bg-base-100 shadow-lg border-t border-base-200 z-50">
      <router-link to="/" class="hover:text-emerald-600" :class="{ 'active text-emerald-600': $route.name === 'Dashboard' }">
        <x-icon name="home" />
        <span class="btm-nav-label text-xs">Beranda</span>
      </router-link>
      <router-link to="/pr" :class="{ 'active text-emerald-600': $route.name?.startsWith('PR') }">
        <x-icon name="document-text" />
        <span class="btm-nav-label text-xs">Daftar PR</span>
      </router-link>
      <router-link to="/penawaran-saya" :class="{ 'active text-emerald-600': $route.name === 'MyQuotations' }">
        <x-icon name="clipboard-list" />
        <span class="btm-nav-label text-xs">Penawaran</span>
      </router-link>
      <router-link to="/invoice" :class="{ 'active text-emerald-600': $route.name === 'MyInvoices' }">
        <x-icon name="receipt" />
        <span class="btm-nav-label text-xs">INVOICE</span>
      </router-link>
      <!-- Notifikasi dengan badge -->
      <button @click="openNotif = true" class="indicator">
        <span v-if="unreadCount > 0" class="indicator-item badge badge-warning badge-xs">{{ unreadCount }}</span>
        <x-icon name="bell" />
        <span class="btm-nav-label text-xs">Notifikasi</span>
      </button>
    </nav>

    <!-- Notifikasi Drawer -->
    <NotificationDrawer v-if="isLoggedIn" v-model="openNotif" />

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore }         from './stores/auth'
import { useProcurementStore }  from './stores/procurement'
import { storeToRefs }          from 'pinia'
import NotificationDrawer       from './components/NotificationDrawer.vue'

const auth        = useAuthStore()
const procurement = useProcurementStore()
const { isLoggedIn }   = storeToRefs(auth)
const { unreadCount }  = storeToRefs(procurement)
const openNotif        = ref(false)

onMounted(async () => {
  if (isLoggedIn.value) {
    await procurement.fetchNotifications()
    // Poll notifikasi setiap 60 detik
    setInterval(() => procurement.fetchNotifications(), 60000)
  }
})
</script>

<style>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
</style>

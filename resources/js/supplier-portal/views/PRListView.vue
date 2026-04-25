<template>
<!--
  FILE: resources/js/supplier-portal/views/PRListView.vue
  PURPOSE: Daftar PR yang didistribusikan ke supplier - dengan filter & search
-->
  <div class="min-h-screen bg-base-200">
    <div class="bg-emerald-700 text-white px-4 pt-10 pb-6">
      <div class="max-w-lg mx-auto">
        <h1 class="text-xl font-bold">Daftar PR</h1>
        <p class="text-emerald-300 text-xs">PR yang dikirimkan ke perusahaan Anda</p>
      </div>
    </div>

    <div class="max-w-lg mx-auto px-4 pt-4 pb-6 space-y-4">

      <!-- Search & Filter -->
      <div class="flex gap-2">
        <input v-model="search" type="text" placeholder="Cari nomor/judul PR..."
               class="input input-bordered input-sm flex-1" @input="debouncedFetch" />
        <select v-model="filterStatus" class="select select-bordered select-sm" @change="fetchPRs">
          <option value="">Semua Status</option>
          <option value="didistribusi">Didistribusi</option>
          <option value="penawaran">Penawaran</option>
          <option value="disetujui">Disetujui</option>
          <option value="selesai">Selesai</option>
        </select>
      </div>

      <!-- Loading Skeleton -->
      <template v-if="loading">
        <div v-for="i in 4" :key="i" class="card bg-white h-28 animate-pulse rounded-xl"></div>
      </template>

      <!-- PR Cards -->
      <template v-else>
        <template v-if="prs?.data?.length > 0">
          <router-link v-for="pr in prs.data" :key="pr.id"
                       :to="`/pr/${pr.id}`"
                       class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow">
            <div class="card-body py-4 px-4">
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-mono text-xs badge badge-outline">{{ pr.pr_number }}</span>
                    <PRStatusBadge :status="pr.status" />
                  </div>
                  <p class="font-semibold text-sm text-base-content leading-snug">{{ pr.title }}</p>
                  <p class="text-xs text-base-content/50 mt-1">{{ pr.category?.name ?? '-' }}</p>
                </div>
                <svg class="w-5 h-5 text-base-content/30 ml-2 mt-1 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </div>
              <div class="flex gap-4 mt-2 text-xs text-base-content/60">
                <span :class="isPastDeadline(pr.deadline) ? 'text-error font-semibold' : ''">
                  ⏰ {{ formatDate(pr.deadline) }}
                </span>
                <span>{{ pr.quotations_count ?? 0 }} penawaran</span>
              </div>
            </div>
          </router-link>

          <!-- Pagination -->
          <div v-if="prs.last_page > 1" class="flex justify-center gap-2 mt-2">
            <button v-for="page in prs.last_page" :key="page"
                    @click="fetchPRs(page)"
                    class="btn btn-sm"
                    :class="page === prs.current_page ? 'btn-primary' : 'btn-ghost'">
              {{ page }}
            </button>
          </div>
        </template>

        <div v-else class="text-center py-16 text-base-content/40">
          <p class="text-4xl mb-3">📭</p>
          <p class="font-medium">Belum ada PR yang dikirimkan.</p>
          <p class="text-sm mt-1">PR akan muncul di sini setelah purchasing mendistribusikannya.</p>
        </div>
      </template>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted }      from 'vue'
import { storeToRefs }         from 'pinia'
import { useProcurementStore } from '../stores/procurement'
import PRStatusBadge           from '../components/PRStatusBadge.vue'

const procurement              = useProcurementStore()
const { prs, loading }         = storeToRefs(procurement)
const search                   = ref('')
const filterStatus             = ref('')
let debounceTimer              = null

function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchPRs(), 400)
}

function fetchPRs(page = 1) {
  procurement.fetchPRs({ search: search.value, status: filterStatus.value, page })
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' })
}
function isPastDeadline(d) { return new Date(d) < new Date() }

onMounted(() => fetchPRs())
</script>

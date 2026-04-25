<template>
<!--
  FILE: resources/js/supplier-portal/views/MyQuotationsView.vue
  PURPOSE: Monitoring status semua penawaran yang pernah dikirim supplier
-->
  <div class="min-h-screen bg-base-200">
    <div class="bg-emerald-700 text-white px-4 pt-10 pb-6">
      <div class="max-w-lg mx-auto">
        <h1 class="text-xl font-bold">Penawaran Saya</h1>
        <p class="text-emerald-300 text-xs">Riwayat dan status penawaran</p>
      </div>
    </div>

    <div class="max-w-lg mx-auto px-4 pt-4 pb-6 space-y-3">

      <div v-if="loading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="card bg-white h-28 animate-pulse rounded-xl"></div>
      </div>

      <template v-else>
        <template v-if="quotations?.data?.length > 0">
          <div v-for="q in quotations.data" :key="q.id"
               class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow">
            <div class="card-body py-4 px-4">
              <div class="flex justify-between items-start gap-2">
                <div class="flex-1 min-w-0">
                  <p class="text-xs text-base-content/50 font-mono">{{ q.purchase_request?.pr_number }}</p>
                  <p class="font-semibold text-sm mt-0.5 leading-snug truncate">{{ q.purchase_request?.title }}</p>
                </div>
                <QuotationStatusBadge :status="q.status" :is-best="q.is_best" />
              </div>

              <div class="grid grid-cols-3 gap-2 mt-2 text-xs">
                <div class="bg-base-200 rounded-lg p-2 text-center">
                  <p class="text-base-content/50">Nilai</p>
                  <p class="font-semibold text-emerald-700">Rp {{ formatMoney(q.total_amount) }}</p>
                </div>
                <div class="bg-base-200 rounded-lg p-2 text-center">
                  <p class="text-base-content/50">Est. Kirim</p>
                  <p class="font-semibold">{{ q.delivery_days }} hr</p>
                </div>
                <div class="bg-base-200 rounded-lg p-2 text-center">
                  <p class="text-base-content/50">Berlaku s/d</p>
                  <p class="font-semibold">{{ shortDate(q.valid_until) }}</p>
                </div>
              </div>

              <p v-if="q.rejection_reason" class="text-xs text-error bg-error/10 rounded p-2 mt-2">
                ❌ {{ q.rejection_reason }}
              </p>

              <p class="text-xs text-base-content/40 mt-1">Dikirim {{ timeAgo(q.created_at) }}</p>
            </div>
          </div>
        </template>

        <div v-else class="text-center py-16 text-base-content/40">
          <p class="text-4xl mb-3">📤</p>
          <p class="font-medium">Belum ada penawaran dikirim.</p>
          <router-link to="/pr" class="btn btn-sm btn-primary mt-4">Lihat Daftar PR</router-link>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useProcurementStore } from '../stores/procurement'
import QuotationStatusBadge from '../components/QuotationStatusBadge.vue'

const procurement  = useProcurementStore()
const { loading }  = storeToRefs(procurement)
const quotations   = ref(null)

onMounted(async () => {
  await procurement.fetchMyQuotations()
  quotations.value = procurement.myQuotations
})

function formatMoney(v)  { return Number(v||0).toLocaleString('id-ID') }
function shortDate(d)    { return new Date(d).toLocaleDateString('id-ID',{day:'numeric',month:'short'}) }
function timeAgo(d) {
  const diff = Date.now() - new Date(d).getTime()
  const days = Math.floor(diff / 86400000)
  return days === 0 ? 'hari ini' : days === 1 ? 'kemarin' : `${days} hari lalu`
}
</script>
<script>
import { ref } from 'vue'
</script>

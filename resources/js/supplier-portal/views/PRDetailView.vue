<template>
<!--
  FILE: resources/js/supplier-portal/views/PRDetailView.vue
  PURPOSE: Detail PR dengan PDF viewer (pdf.js) dan tombol submit penawaran
-->
  <div class="min-h-screen bg-base-200">

    <!-- Header -->
    <div class="bg-emerald-700 text-white px-4 pt-10 pb-4">
      <div class="max-w-lg mx-auto flex items-center gap-3">
        <button @click="$router.back()" class="btn btn-ghost btn-sm btn-circle text-white">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <div>
          <h1 class="text-lg font-bold leading-tight">{{ pr?.purchase_request?.pr_number ?? 'Memuat...' }}</h1>
          <p class="text-emerald-300 text-xs">Detail Purchase Request</p>
        </div>
      </div>
    </div>

    <div class="max-w-lg mx-auto px-4 pt-4 pb-6 space-y-4">

      <!-- Loading -->
      <div v-if="loading" class="space-y-3">
        <div class="card bg-white h-32 animate-pulse rounded-xl"></div>
        <div class="card bg-white h-64 animate-pulse rounded-xl"></div>
      </div>

      <template v-else-if="pr">
        <!-- Info Card -->
        <div class="card bg-base-100 shadow-sm">
          <div class="card-body py-4">
            <div class="flex justify-between items-start">
              <div>
                <h2 class="font-bold text-base leading-snug">{{ pr.purchase_request.title }}</h2>
                <p class="text-xs text-base-content/60 mt-1">{{ pr.purchase_request.category?.name ?? '-' }}</p>
              </div>
              <PRStatusBadge :status="pr.purchase_request.status" />
            </div>
            <div class="divider my-2"></div>
            <div class="grid grid-cols-2 gap-y-2 text-sm">
              <div>
                <p class="text-xs text-base-content/50">Deadline</p>
                <p :class="isPastDeadline ? 'text-error font-semibold' : 'font-medium'">
                  {{ formatDate(pr.purchase_request.deadline) }}
                </p>
              </div>
              <div>
                <p class="text-xs text-base-content/50">Status PR</p>
                <p class="font-medium capitalize">{{ pr.purchase_request.status }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- PDF Viewer -->
        <div class="card bg-base-100 shadow-sm">
          <div class="card-body py-4">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold text-sm">📄 Dokumen PR (PDF dari ERP)</h3>
              <a :href="pdfUrl" target="_blank" class="btn btn-xs btn-outline btn-primary">
                Download
              </a>
            </div>

            <!-- PDF Canvas Viewer (pdf.js) -->
            <div class="bg-gray-100 rounded-lg overflow-hidden min-h-64 relative" ref="pdfContainer">
              <div v-if="pdfLoading" class="absolute inset-0 flex items-center justify-center bg-gray-100">
                <span class="loading loading-spinner loading-md text-primary"></span>
              </div>
              <canvas ref="pdfCanvas" class="w-full"></canvas>

              <!-- Nav halaman PDF -->
              <div v-if="totalPages > 1" class="flex items-center justify-center gap-3 py-2 bg-base-100 border-t text-sm">
                <button @click="prevPage" :disabled="currentPage <= 1" class="btn btn-xs btn-ghost">◀</button>
                <span>{{ currentPage }} / {{ totalPages }}</span>
                <button @click="nextPage" :disabled="currentPage >= totalPages" class="btn btn-xs btn-ghost">▶</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Status Penawaran Saya -->
        <div v-if="pr.my_quotation" class="card bg-emerald-50 border border-emerald-200 shadow-sm">
          <div class="card-body py-4">
            <div class="flex items-start gap-3">
              <span class="text-2xl">✅</span>
              <div>
                <h3 class="font-semibold text-emerald-800">Penawaran Sudah Dikirim</h3>
                <p class="text-sm text-emerald-700">Total: <strong>Rp {{ formatMoney(pr.my_quotation.total_amount) }}</strong></p>
                <p class="text-xs text-emerald-600 mt-1">
                  Status: <span class="font-semibold">{{ statusLabel(pr.my_quotation.status) }}</span>
                </p>
                <p v-if="pr.my_quotation.is_best" class="text-xs text-amber-600 font-semibold mt-1">⭐ Penawaran Anda adalah yang terbaik!</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Tombol Submit Penawaran -->
        <div v-if="pr.can_submit">
          <router-link :to="`/pr/${prId}/penawaran`" class="btn btn-primary btn-block">
            📤 Kirim Penawaran
          </router-link>
        </div>
        <div v-else-if="isPastDeadline && !pr.my_quotation" class="alert alert-warning text-sm">
          ⏰ Deadline sudah lewat. Penawaran tidak dapat dikirim.
        </div>

      </template>

      <!-- Error State -->
      <div v-else-if="error" class="alert alert-error">{{ error }}</div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute }                        from 'vue-router'
import { useProcurementStore }             from '../stores/procurement'
import { storeToRefs }                     from 'pinia'
import PRStatusBadge                       from '../components/PRStatusBadge.vue'

const route       = useRoute()
const prId        = route.params.id
const procurement = useProcurementStore()
const { loading, error } = storeToRefs(procurement)
const pr          = ref(null)

// PDF.js state
const pdfCanvas    = ref(null)
const pdfLoading   = ref(false)
const currentPage  = ref(1)
const totalPages   = ref(1)
let   pdfDoc       = null

const isPastDeadline = computed(() =>
  pr.value ? new Date(pr.value.purchase_request.deadline) < new Date() : false
)

const pdfUrl = computed(() =>
  pr.value ? `/api/v1/purchase-requests/${prId}/download` : null
)

async function loadPDF(url) {
  if (!window.pdfjsLib) {
    // Lazy load pdf.js dari CDN
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.min.mjs')
    window.pdfjsLib.GlobalWorkerOptions.workerSrc =
      'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.worker.min.mjs'
  }
  pdfLoading.value = true
  try {
    // Fetch dengan header Authorization (token Sanctum)
    const token = localStorage.getItem('supplier_token')
    pdfDoc = await window.pdfjsLib.getDocument({
      url,
      httpHeaders: { Authorization: `Bearer ${token}` },
    }).promise
    totalPages.value = pdfDoc.numPages
    await renderPage(1)
  } catch (e) {
    console.error('PDF load error:', e)
  } finally {
    pdfLoading.value = false
  }
}

async function renderPage(pageNum) {
  if (!pdfDoc || !pdfCanvas.value) return
  const page     = await pdfDoc.getPage(pageNum)
  const viewport = page.getViewport({ scale: 1.5 })
  const canvas   = pdfCanvas.value
  const ctx      = canvas.getContext('2d')
  canvas.height  = viewport.height
  canvas.width   = viewport.width
  await page.render({ canvasContext: ctx, viewport }).promise
}

function prevPage() { if (currentPage.value > 1) { currentPage.value--; renderPage(currentPage.value) } }
function nextPage() { if (currentPage.value < totalPages.value) { currentPage.value++; renderPage(currentPage.value) } }

function loadScript(src) {
  return new Promise((res, rej) => {
    const s = document.createElement('script'); s.src = src; s.type = 'module'
    s.onload = res; s.onerror = rej; document.head.appendChild(s)
  })
}

function formatDate(d)  { return new Date(d).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) }
function formatMoney(v) { return Number(v).toLocaleString('id-ID') }
function statusLabel(s) {
  return { submitted:'Menunggu Review', review:'Dalam Review', selected:'Diterima 🎉',
           rejected:'Ditolak', revised:'Revisi Diperlukan' }[s] ?? s
}

onMounted(async () => {
  pr.value = await procurement.fetchPRDetail(prId)
  if (pr.value) loadPDF(pdfUrl.value)
})
</script>

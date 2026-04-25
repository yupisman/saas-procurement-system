<template>
<!--
  FILE: resources/js/supplier-portal/views/SubmitQuotationView.vue
  PURPOSE: Form submit penawaran - multi-item, file upload, offline draft (Capacitor-ready)
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
          <h1 class="text-lg font-bold">Submit Penawaran</h1>
          <p class="text-emerald-300 text-xs">PR: {{ prId }}</p>
        </div>
      </div>
    </div>

    <div class="max-w-lg mx-auto px-4 pt-4 pb-8 space-y-4">

      <!-- Draft Saved Indicator -->
      <div v-if="draftSaved" class="alert alert-info text-xs py-2">
        💾 Draft tersimpan otomatis — {{ draftTime }}
      </div>

      <!-- Error -->
      <div v-if="submitError" class="alert alert-error text-sm">{{ submitError }}</div>

      <!-- Success -->
      <div v-if="submitted" class="card bg-success text-success-content">
        <div class="card-body text-center py-8">
          <p class="text-4xl mb-2">🎉</p>
          <h3 class="text-xl font-bold">Penawaran Terkirim!</h3>
          <p class="text-sm opacity-80">Penawaran Anda telah diterima. Harap menunggu konfirmasi dari purchasing.</p>
          <button @click="$router.push('/penawaran-saya')" class="btn btn-sm mt-4 bg-white text-success">
            Lihat Status Penawaran
          </button>
        </div>
      </div>

      <template v-else>

        <!-- ── SECTION 1: Info Penawaran ── -->
        <div class="card bg-base-100 shadow-sm">
          <div class="card-body py-4">
            <h3 class="font-semibold text-sm mb-3 text-base-content/80">📋 Informasi Penawaran</h3>
            <div class="space-y-3">

              <div class="form-control">
                <label class="label pb-1"><span class="label-text text-xs font-medium">No. Penawaran Perusahaan</span></label>
                <input v-model="form.quotation_number" type="text" class="input input-bordered input-sm"
                       placeholder="Optional (misal: QUO/2024/001)" @input="saveDraft" />
              </div>

              <div class="form-control">
                <label class="label pb-1">
                  <span class="label-text text-xs font-medium">Total Nilai Penawaran (Rp) <span class="text-error">*</span></span>
                </label>
                <input v-model.number="form.total_amount" type="number" min="0"
                       class="input input-bordered input-sm" :class="{'input-error': errors.total_amount}"
                       placeholder="0" @input="saveDraft" />
                <div class="label" v-if="form.total_amount > 0">
                  <span class="label-text-alt text-success">Rp {{ formatMoney(form.total_amount) }}</span>
                </div>
                <span v-if="errors.total_amount" class="text-error text-xs">{{ errors.total_amount[0] }}</span>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div class="form-control">
                  <label class="label pb-1"><span class="label-text text-xs font-medium">Est. Pengiriman (hari) <span class="text-error">*</span></span></label>
                  <input v-model.number="form.delivery_days" type="number" min="1" max="365"
                         class="input input-bordered input-sm" :class="{'input-error': errors.delivery_days}"
                         placeholder="7" @input="saveDraft" />
                </div>
                <div class="form-control">
                  <label class="label pb-1"><span class="label-text text-xs font-medium">Penawaran Berlaku Hingga <span class="text-error">*</span></span></label>
                  <input v-model="form.valid_until" type="date"
                         class="input input-bordered input-sm" :class="{'input-error': errors.valid_until}"
                         :min="minDate" @change="saveDraft" />
                </div>
              </div>

              <div class="form-control">
                <label class="label pb-1"><span class="label-text text-xs font-medium">Syarat & Ketentuan</span></label>
                <textarea v-model="form.terms" class="textarea textarea-bordered textarea-sm" rows="3"
                          placeholder="Pembayaran NET 30, FOB Gudang Supplier, dll..." @input="saveDraft"></textarea>
              </div>

              <div class="form-control">
                <label class="label pb-1"><span class="label-text text-xs font-medium">Catatan Tambahan</span></label>
                <textarea v-model="form.notes" class="textarea textarea-bordered textarea-sm" rows="2"
                          placeholder="Catatan untuk purchasing..." @input="saveDraft"></textarea>
              </div>

            </div>
          </div>
        </div>

        <!-- ── SECTION 2: Rincian Item ── -->
        <div class="card bg-base-100 shadow-sm">
          <div class="card-body py-4">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold text-sm text-base-content/80">📦 Rincian Item</h3>
              <button @click="addItem" type="button" class="btn btn-xs btn-outline btn-primary">+ Tambah</button>
            </div>
            <span v-if="errors.items" class="text-error text-xs">{{ errors.items[0] }}</span>

            <div v-for="(item, idx) in form.items" :key="idx"
                 class="border border-base-200 rounded-lg p-3 space-y-2 relative mb-3">
              <button v-if="form.items.length > 1"
                      @click="removeItem(idx)" type="button"
                      class="absolute top-2 right-2 btn btn-ghost btn-xs text-error">✕</button>

              <p class="text-xs font-semibold text-base-content/60">Item {{ idx + 1 }}</p>

              <input v-model="item.item_name" type="text" placeholder="Nama barang/jasa *"
                     class="input input-bordered input-xs w-full" @input="saveDraft" />

              <div class="grid grid-cols-3 gap-2">
                <input v-model.number="item.quantity" type="number" min="0" step="0.01" placeholder="Qty *"
                       class="input input-bordered input-xs" @input="recalcItem(idx); saveDraft()" />
                <input v-model="item.unit" type="text" placeholder="Satuan *"
                       class="input input-bordered input-xs" @input="saveDraft" />
                <input v-model.number="item.unit_price" type="number" min="0" placeholder="Harga/unit *"
                       class="input input-bordered input-xs" @input="recalcItem(idx); saveDraft()" />
              </div>

              <div class="flex justify-between items-center text-xs text-base-content/60 px-1">
                <span>Subtotal:</span>
                <span class="font-semibold text-emerald-600">Rp {{ formatMoney(item.total_price) }}</span>
              </div>

              <textarea v-model="item.specifications" placeholder="Spesifikasi teknis (opsional)"
                        class="textarea textarea-bordered textarea-xs w-full" rows="2" @input="saveDraft"></textarea>
            </div>

            <!-- Grand Total -->
            <div class="flex justify-between items-center font-bold text-sm bg-emerald-50 rounded-lg px-3 py-2 mt-1">
              <span>Total Keseluruhan:</span>
              <span class="text-emerald-700">Rp {{ formatMoney(grandTotal) }}</span>
            </div>
          </div>
        </div>

        <!-- ── SECTION 3: Upload Dokumen ── -->
        <div class="card bg-base-100 shadow-sm">
          <div class="card-body py-4">
            <h3 class="font-semibold text-sm mb-3 text-base-content/80">📎 Dokumen Pendukung (maks. 5 file)</h3>
            <p class="text-xs text-base-content/50 mb-3">PDF, Excel, atau gambar. Maks 10MB per file.</p>

            <!-- File input (mendukung kamera di Capacitor) -->
            <label class="border-2 border-dashed border-base-300 rounded-xl p-6 flex flex-col items-center cursor-pointer hover:border-primary transition-colors">
              <input type="file" class="hidden" multiple accept=".pdf,.xlsx,.xls,.jpg,.jpeg,.png"
                     @change="handleFiles" />
              <span class="text-3xl mb-2">📁</span>
              <p class="text-sm text-primary font-medium">Pilih File / Foto</p>
              <p class="text-xs text-base-content/40 mt-1">atau seret ke sini</p>
            </label>

            <!-- Preview file yang dipilih -->
            <div v-if="selectedFiles.length > 0" class="space-y-2 mt-3">
              <div v-for="(f, i) in selectedFiles" :key="i"
                   class="flex items-center gap-2 bg-base-200 rounded-lg px-3 py-2">
                <span class="text-lg">{{ fileIcon(f.type) }}</span>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-medium truncate">{{ f.name }}</p>
                  <p class="text-xs text-base-content/50">{{ formatFileSize(f.size) }}</p>
                </div>
                <select v-model="fileCategories[i]" class="select select-xs select-bordered">
                  <option value="penawaran_harga">Harga</option>
                  <option value="spesifikasi">Spesifikasi</option>
                  <option value="sertifikat">Sertifikat</option>
                  <option value="lainnya">Lainnya</option>
                </select>
                <button @click="removeFile(i)" type="button" class="btn btn-ghost btn-xs text-error">✕</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <button @click="handleSubmit" :disabled="submitting"
                class="btn btn-primary btn-block text-base h-14">
          <span v-if="submitting" class="loading loading-spinner"></span>
          <span v-else>📤 Kirim Penawaran Sekarang</span>
        </button>

        <p class="text-center text-xs text-base-content/40">
          Pastikan semua data sudah benar sebelum mengirim.<br>
          Penawaran tidak dapat diubah setelah dikirim.
        </p>

      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter }                from 'vue-router'
import { useProcurementStore }                from '../stores/procurement'

const route        = useRoute()
const router       = useRouter()
const procurement  = useProcurementStore()
const prId         = route.params.prId

const submitted    = ref(false)
const submitting   = ref(false)
const submitError  = ref('')
const errors       = ref({})
const draftSaved   = ref(false)
const draftTime    = ref('')
const selectedFiles   = ref([])
const fileCategories  = ref([])

// Form state
const form = reactive({
  quotation_number : '',
  total_amount     : 0,
  delivery_days    : 7,
  valid_until      : '',
  terms            : '',
  notes            : '',
  items            : [emptyItem()],
})

function emptyItem() {
  return { item_name: '', unit: '', quantity: 1, unit_price: 0, total_price: 0, specifications: '' }
}

const minDate    = computed(() => new Date(Date.now() + 86400000).toISOString().split('T')[0])
const grandTotal = computed(() => form.items.reduce((s, i) => s + (i.total_price || 0), 0))

function recalcItem(idx) {
  form.items[idx].total_price = (form.items[idx].quantity || 0) * (form.items[idx].unit_price || 0)
}
function addItem()        { form.items.push(emptyItem()) }
function removeItem(idx)  { form.items.splice(idx, 1) }

function handleFiles(e) {
  const files = Array.from(e.target.files)
  const remaining = 5 - selectedFiles.value.length
  files.slice(0, remaining).forEach(f => {
    selectedFiles.value.push(f)
    fileCategories.value.push('penawaran_harga')
  })
}
function removeFile(i) {
  selectedFiles.value.splice(i, 1)
  fileCategories.value.splice(i, 1)
}

// ── Draft: simpan ke localStorage setiap perubahan ───────────────────────────
function saveDraft() {
  procurement.saveDraft(prId, { ...form })
  draftSaved.value = true
  draftTime.value  = new Date().toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' })
}

// Load draft jika ada
onMounted(() => {
  const draft = procurement.quotationDraft[prId]
  if (draft) {
    Object.assign(form, draft)
    draftSaved.value = true
    draftTime.value  = new Date(draft.savedAt).toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' })
  }
})

// ── Submit Form ───────────────────────────────────────────────────────────────
async function handleSubmit() {
  submitting.value = true
  submitError.value = ''
  errors.value = {}

  const fd = new FormData()
  fd.append('total_amount',    form.total_amount)
  fd.append('delivery_days',   form.delivery_days)
  fd.append('valid_until',     form.valid_until)
  if (form.quotation_number) fd.append('quotation_number', form.quotation_number)
  if (form.terms)            fd.append('terms', form.terms)
  if (form.notes)            fd.append('notes', form.notes)

  form.items.forEach((item, i) => {
    Object.entries(item).forEach(([k, v]) => fd.append(`items[${i}][${k}]`, v))
  })

  selectedFiles.value.forEach((file, i) => {
    fd.append(`files[${i}]`,           file)
    fd.append(`file_categories[${i}]`, fileCategories.value[i])
  })

  try {
    await procurement.submitQuotation(prId, fd)
    submitted.value = true
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value    = e.response.data.errors ?? {}
      submitError.value = e.response.data.message
    } else {
      submitError.value = e.response?.data?.message ?? 'Gagal mengirim penawaran. Coba lagi.'
    }
  } finally {
    submitting.value = false
  }
}

function formatMoney(v)    { return Number(v || 0).toLocaleString('id-ID') }
function formatFileSize(s) { return s >= 1048576 ? (s/1048576).toFixed(1)+'MB' : (s/1024).toFixed(0)+'KB' }
function fileIcon(type)    {
  if (type?.includes('pdf'))   return '📄'
  if (type?.includes('image')) return '🖼️'
  if (type?.includes('sheet') || type?.includes('excel')) return '📊'
  return '📎'
}
</script>

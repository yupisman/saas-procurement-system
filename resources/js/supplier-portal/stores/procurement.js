import { defineStore } from 'pinia'
import { ref }         from 'vue'
import axios           from 'axios'

export const useProcurementStore = defineStore('procurement', () => {
    const prs              = ref([])
    const currentPR        = ref(null)
    const myQuotations     = ref([])
    const dashboardStats   = ref(null)
    const notifications    = ref([])
    const unreadCount      = ref(0)
    const loading          = ref(false)
    const error            = ref(null)

    const quotationDraft = ref(
        JSON.parse(localStorage.getItem('quotation_draft') || '{}')
    )

    function saveDraft(prId, data) {
        quotationDraft.value[prId] = { ...data, savedAt: new Date().toISOString() }
        localStorage.setItem('quotation_draft', JSON.stringify(quotationDraft.value))
    }

    function clearDraft(prId) {
        delete quotationDraft.value[prId]
        localStorage.setItem('quotation_draft', JSON.stringify(quotationDraft.value))
    }

    async function fetchDashboard() {
        loading.value = true
        try {
            const { data } = await axios.get('/dashboard')
            dashboardStats.value = data
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Gagal memuat dashboard.'
        } finally {
            loading.value = false
        }
    }

    async function fetchPRs(params = {}) {
        loading.value = true
        error.value   = null
        try {
            const { data } = await axios.get('/purchase-requests', { params })
            prs.value = data
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Gagal memuat daftar PR.'
        } finally {
            loading.value = false
        }
    }

    async function fetchPRDetail(id) {
        loading.value   = true
        currentPR.value = null
        try {
            const { data } = await axios.get(`/purchase-requests/${id}`)
            currentPR.value = data
            return data
        } catch (e) {
            error.value = e.response?.data?.message ?? 'PR tidak ditemukan.'
            throw e
        } finally {
            loading.value = false
        }
    }

    async function submitQuotation(prId, formData) {
        const { data } = await axios.post(`/quotations/pr/${prId}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        clearDraft(prId)
        return data
    }

    async function fetchMyQuotations() {
        const { data } = await axios.get('/quotations/my')
        myQuotations.value = data
    }

    async function fetchNotifications() {
        const { data }   = await axios.get('/notifications')
        notifications.value = data.notifications.data
        unreadCount.value   = data.unread_count
    }

    async function markAllRead() {
        await axios.post('/notifications/mark-all-read')
        unreadCount.value = 0
        notifications.value.forEach(n => n.is_read = true)
    }

    return {
        prs, currentPR, myQuotations, dashboardStats, notifications,
        unreadCount, loading, error, quotationDraft,
        saveDraft, clearDraft,
        fetchDashboard, fetchPRs, fetchPRDetail, submitQuotation,
        fetchMyQuotations, fetchNotifications, markAllRead,
    }
})

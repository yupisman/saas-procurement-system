import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
    const token = ref(localStorage.getItem('supplier_token') || null)
    const user  = ref(JSON.parse(localStorage.getItem('supplier_user') || 'null'))

    const isLoggedIn = computed(() => !!token.value)
    const supplier   = computed(() => user.value?.supplier ?? null)

    async function login(email, password) {
        const { data } = await axios.post('/auth/login', {
            email,
            password,
            device_name: 'supplier-portal-web',
        })

        token.value = data.token
        user.value  = data.user

        localStorage.setItem('supplier_token', data.token)
        localStorage.setItem('supplier_user', JSON.stringify(data.user))

        axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
    }

    async function logout() {
        try {
            await axios.post('/auth/logout')
        } catch (e) {}
        token.value = null
        user.value  = null
        localStorage.removeItem('supplier_token')
        localStorage.removeItem('supplier_user')
        delete axios.defaults.headers.common['Authorization']
    }

    async function fetchMe() {
        const { data } = await axios.get('/auth/me')
        user.value = data.user
        localStorage.setItem('supplier_user', JSON.stringify(data.user))
    }

    return { token, user, isLoggedIn, supplier, login, logout, fetchMe }
})

import { createApp }   from 'vue'
import { createPinia } from 'pinia'
import App             from './App.vue'
import router          from './router'
import axios           from 'axios'

axios.defaults.baseURL = import.meta.env.VITE_API_URL + '/api/v1'
axios.defaults.headers.common['Accept']       = 'application/json'
axios.defaults.headers.common['Content-Type'] = 'application/json'

axios.interceptors.request.use(config => {
    const token = localStorage.getItem('supplier_token')
    if (token) config.headers['Authorization'] = `Bearer ${token}`
    return config
})

axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            localStorage.removeItem('supplier_token')
            localStorage.removeItem('supplier_user')
            router.push({ name: 'Login' })
        }
        return Promise.reject(error)
    }
)

const app   = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

app.config.globalProperties.$axios = axios

app.mount('#app')

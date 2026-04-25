import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore }                    from '../stores/auth'

const LoginView        = () => import('../views/LoginView.vue')
const DashboardView    = () => import('../views/DashboardView.vue')
const PRListView       = () => import('../views/PRListView.vue')
const PRDetailView     = () => import('../views/PRDetailView.vue')
const SubmitQuotation  = () => import('../views/SubmitQuotationView.vue')
const MyQuotations     = () => import('../views/MyQuotationsView.vue')
const MyDeliveries     = () => import('../views/MyDeliveriesView.vue')
const MyInvoices       = () => import('../views/MyInvoicesView.vue')
const ProfileView      = () => import('../views/ProfileView.vue')

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: LoginView,
        meta: { public: true, title: 'Login' },
    },
    {
        path: '/',
        name: 'Dashboard',
        component: DashboardView,
        meta: { requiresAuth: true, title: 'Dashboard' },
    },
    {
        path: '/pr',
        name: 'PRList',
        component: PRListView,
        meta: { requiresAuth: true, title: 'Daftar PR' },
    },
    {
        path: '/pr/:id',
        name: 'PRDetail',
        component: PRDetailView,
        meta: { requiresAuth: true, title: 'Detail PR' },
    },
    {
        path: '/pr/:prId/penawaran',
        name: 'SubmitQuotation',
        component: SubmitQuotation,
        meta: { requiresAuth: true, title: 'Submit Penawaran' },
    },
    {
        path: '/penawaran-saya',
        name: 'MyQuotations',
        component: MyQuotations,
        meta: { requiresAuth: true, title: 'Penawaran Saya' },
    },
    {
        path: '/pengiriman',
        name: 'MyDeliveries',
        component: MyDeliveries,
        meta: { requiresAuth: true, title: 'Pengiriman' },
    },
    {
        path: '/invoice',
        name: 'MyInvoices',
        component: MyInvoices,
        meta: { requiresAuth: true, title: 'INVOICE & FAKTUR PAJAK' },
    },
    {
        path: '/profil',
        name: 'Profile',
        component: ProfileView,
        meta: { requiresAuth: true, title: 'Profil' },
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to, _from, next) => {
    const auth = useAuthStore()

    document.title = (to.meta.title ? `${to.meta.title} - ` : '') + 'Portal Supplier'

    if (to.meta.requiresAuth && !auth.isLoggedIn) {
        next({ name: 'Login', query: { redirect: to.fullPath } })
    } else if (to.name === 'Login' && auth.isLoggedIn) {
        next({ name: 'Dashboard' })
    } else {
        next()
    }
})

export default router

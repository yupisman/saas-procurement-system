<template>
<!--
  FILE: resources/js/supplier-portal/views/LoginView.vue
  PURPOSE: Halaman login supplier dengan validasi, error handling, loading state
-->
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-emerald-50 to-teal-100 p-4">
    <div class="card w-full max-w-sm shadow-2xl bg-base-100">
      <div class="card-body">

        <!-- Logo / Brand -->
        <div class="text-center mb-4">
          <div class="w-16 h-16 bg-emerald-600 rounded-2xl mx-auto flex items-center justify-center shadow-lg mb-3">
            <svg class="w-9 h-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-base-content">Portal Supplier</h1>
          <p class="text-sm text-base-content/60 mt-1">Sistem Pengadaan — Login Akun Anda</p>
        </div>

        <!-- Form Login -->
        <form @submit.prevent="handleLogin" class="space-y-4">

          <!-- Error Alert -->
          <div v-if="errorMsg" class="alert alert-error text-sm py-2">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ errorMsg }}
          </div>

          <!-- Email -->
          <div class="form-control">
            <label class="label pb-1">
              <span class="label-text font-medium">Email</span>
            </label>
            <input v-model="form.email"
                   type="email"
                   class="input input-bordered"
                   :class="{ 'input-error': errors.email }"
                   placeholder="nama@perusahaan.com"
                   autocomplete="username"
                   required />
            <span v-if="errors.email" class="label-text-alt text-error mt-1">{{ errors.email[0] }}</span>
          </div>

          <!-- Password -->
          <div class="form-control">
            <label class="label pb-1">
              <span class="label-text font-medium">Password</span>
            </label>
            <div class="relative">
              <input v-model="form.password"
                     :type="showPwd ? 'text' : 'password'"
                     class="input input-bordered w-full pr-10"
                     :class="{ 'input-error': errors.password }"
                     placeholder="••••••••"
                     autocomplete="current-password"
                     required />
              <button type="button" @click="showPwd = !showPwd"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path v-if="showPwd" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29"/>
                  <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Submit -->
          <button type="submit"
                  class="btn btn-primary w-full"
                  :disabled="loading">
            <span v-if="loading" class="loading loading-spinner loading-sm"></span>
            <span v-else>Masuk</span>
          </button>

        </form>

        <p class="text-center text-xs text-base-content/40 mt-4">
          Lupa password? Hubungi admin pengadaan.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth    = useAuthStore()
const router  = useRouter()
const route   = useRoute()

const form    = reactive({ email: '', password: '' })
const loading = ref(false)
const showPwd = ref(false)
const errorMsg = ref('')
const errors   = ref({})

async function handleLogin() {
  loading.value  = true
  errorMsg.value = ''
  errors.value   = {}

  try {
    await auth.login(form.email, form.password)
    // Redirect ke halaman sebelumnya atau dashboard
    const redirect = route.query.redirect || '/'
    router.push(redirect)
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors ?? {}
      errorMsg.value = e.response.data.message ?? 'Validasi gagal.'
    } else if (e.response?.status === 403) {
      errorMsg.value = e.response.data.message
    } else {
      errorMsg.value = 'Gagal terhubung ke server. Periksa koneksi internet Anda.'
    }
  } finally {
    loading.value = false
  }
}
</script>

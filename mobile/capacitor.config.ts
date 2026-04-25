// =============================================================================
// FILE: mobile/capacitor.config.ts
// PURPOSE: Konfigurasi Capacitor untuk Android build dari Vue supplier portal
// =============================================================================
import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  // ── App Identity ──────────────────────────────────────────────────────────
  appId:   'com.perusahaan.supplierportal',
  appName: 'Portal Supplier',

  // ── Web dir: hasil build Vite ──────────────────────────────────────────────
  webDir: 'src/dist',

  // ── Server: saat development, gunakan URL dev server ─────────────────────
  // Uncomment untuk live reload saat dev:
  // server: {
  //   url: 'http://192.168.1.x:3000',
  //   cleartext: true,
  // },

  // ── Plugin configurations ─────────────────────────────────────────────────
  plugins: {
    // Kamera: izin untuk upload foto bukti pengiriman
    Camera: {
      presentationStyle: 'fullscreen',
    },

    // Filesystem: untuk cache PDF PR di device
    Filesystem: {
      // Android: gunakan DOCUMENTS dir agar bisa diakses user
    },

    // Network: deteksi koneksi internet (untuk offline draft logic)
    Network: {},

    // Preferences: storage key-value untuk auth token dan draft
    // Lebih aman dari localStorage untuk mobile
    Preferences: {},

    // HTTP: interceptor untuk API calls
    CapacitorHttp: {
      enabled: true,
    },

    // Status bar
    StatusBar: {
      style: 'dark',
      backgroundColor: '#059669', // Emerald - sesuai theme
    },

    // Splash screen
    SplashScreen: {
      launchShowDuration: 2000,
      backgroundColor:    '#059669',
      androidScaleType:   'CENTER_CROP',
      showSpinner:        false,
    },
  },

  // ── Android specific ──────────────────────────────────────────────────────
  android: {
    // Izinkan HTTP cleartext untuk dev (disable di production)
    allowMixedContent: false,
  },
}

export default config


// =============================================================================
// FILE: mobile/src/capacitor-bridge.js
// PURPOSE: Bridge layer antara Vue app dan Capacitor native APIs
//          Digunakan untuk: upload foto, storage token, network check
// =============================================================================

import { Camera, CameraResultType, CameraSource } from '@capacitor/camera'
import { Filesystem, Directory, Encoding }         from '@capacitor/filesystem'
import { Network }                                 from '@capacitor/network'
import { Preferences }                             from '@capacitor/preferences'

/**
 * Cek apakah berjalan di dalam Capacitor (native) atau browser biasa
 */
export const isNative = () => {
  return typeof window !== 'undefined' && window.Capacitor?.isNative === true
}

/**
 * Simpan auth token menggunakan Preferences (lebih aman dari localStorage di mobile)
 */
export const tokenStorage = {
  async set(token) {
    if (isNative()) {
      await Preferences.set({ key: 'supplier_token', value: token })
    } else {
      localStorage.setItem('supplier_token', token)
    }
  },
  async get() {
    if (isNative()) {
      const { value } = await Preferences.get({ key: 'supplier_token' })
      return value
    }
    return localStorage.getItem('supplier_token')
  },
  async remove() {
    if (isNative()) {
      await Preferences.remove({ key: 'supplier_token' })
    } else {
      localStorage.removeItem('supplier_token')
    }
  },
}

/**
 * Ambil foto dari kamera ATAU pilih dari galeri (untuk bukti pengiriman).
 * Mengembalikan File object yang bisa langsung di-append ke FormData.
 *
 * @param  {'camera'|'gallery'}  source
 * @returns  Promise<File|null>
 */
export async function pickPhoto(source = 'camera') {
  if (!isNative()) {
    // Di browser: trigger input[type=file]
    return new Promise((resolve) => {
      const input = document.createElement('input')
      input.type  = 'file'
      input.accept = 'image/*'
      if (source === 'camera') input.capture = 'environment'
      input.onchange = (e) => resolve(e.target.files?.[0] ?? null)
      input.click()
    })
  }

  // Di Capacitor native: gunakan Camera plugin
  const image = await Camera.getPhoto({
    quality:      80,
    allowEditing:  false,
    resultType:   CameraResultType.DataUrl,
    source:       source === 'camera' ? CameraSource.Camera : CameraSource.Photos,
  })

  // Convert DataURL ke File
  if (image.dataUrl) {
    const res  = await fetch(image.dataUrl)
    const blob = await res.blob()
    return new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' })
  }
  return null
}

/**
 * Cek status koneksi internet.
 * Digunakan sebelum submit penawaran agar user tahu jika offline.
 *
 * @returns  Promise<boolean>
 */
export async function isOnline() {
  if (isNative()) {
    const status = await Network.getStatus()
    return status.connected
  }
  return navigator.onLine
}

/**
 * Download dan cache PDF PR di local device storage.
 * Berguna untuk akses offline saat tidak ada internet.
 *
 * @param  string  url    URL download PDF (API)
 * @param  string  fileName
 */
export async function cachePDF(url, fileName) {
  if (!isNative()) return null

  const token = await tokenStorage.get()
  try {
    const result = await Filesystem.downloadFile({
      url,
      path:       `pr_cache/${fileName}`,
      directory:  Directory.Cache,
      headers:    { Authorization: `Bearer ${token}` },
    })
    return result.path
  } catch (e) {
    console.error('PDF cache error:', e)
    return null
  }
}

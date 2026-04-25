// =============================================================================
// FILE: vite.config.js
// PURPOSE: Vite build config untuk Laravel + Vue 3 + Capacitor-ready output
// =============================================================================
import { defineConfig, loadEnv } from 'vite'
import laravel  from 'laravel-vite-plugin'
import vue      from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [
      // Laravel Vite plugin untuk hot reload
      laravel({
        input: [
          'resources/css/app.css',
          'resources/js/app.js',                            // Filament / Blade
          'resources/js/supplier-portal/main.js',           // Supplier Vue portal
        ],
        refresh: true,
      }),

      // Vue 3 support
      vue({
        template: {
          transformAssetUrls: {
            base: null,
            includeAbsolute: false,
          },
        },
      }),
    ],

    resolve: {
      alias: {
        '@'        : resolve(__dirname, 'resources/js/supplier-portal'),
        '@views'   : resolve(__dirname, 'resources/js/supplier-portal/views'),
        '@stores'  : resolve(__dirname, 'resources/js/supplier-portal/stores'),
        '@components': resolve(__dirname, 'resources/js/supplier-portal/components'),
      },
    },

    // ── Untuk Capacitor: build ke /mobile/src/dist ──────────────────────────
    // Uncomment saat build untuk mobile:
    // build: {
    //   outDir: 'mobile/src/dist',
    //   emptyOutDir: true,
    // },

    server: {
      port: 3000,
      host: true,
    },

    // Optimize dependencies yang sering dipakai
    optimizeDeps: {
      include: ['vue', 'pinia', 'vue-router', 'axios'],
    },
  }
})

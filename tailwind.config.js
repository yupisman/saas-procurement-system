/** @type {import('tailwindcss').Config} */
// =============================================================================
// FILE: tailwind.config.js
// PURPOSE: Tailwind + DaisyUI config dengan tema procurement
//          Warna: Emerald (primary), Dark Red (alert), Gold (accent)
// =============================================================================
export default {
  // Scan semua Blade, Vue, dan JS file untuk purging
  content: [
    './resources/**/*.{blade.php,js,vue}',
    './app/Filament/**/*.php',
    './app/Livewire/**/*.php',
    './vendor/filament/**/*.blade.php',
  ],

  theme: {
    extend: {
      // ── Font ──────────────────────────────────────────────────────────────
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Fira Code', 'monospace'],
      },

      // ── Custom colors procurement ────────────────────────────────────────
      colors: {
        procurement: {
          primary: '#059669',  // Emerald-600
          danger:  '#991b1b',  // Dark Red / Red-800
          gold:    '#d97706',  // Amber-600
          dark:    '#1f2937',  // Gray-800
        },
      },

      // ── Animation untuk loading states ───────────────────────────────────
      animation: {
        'fade-in': 'fadeIn 0.2s ease-in-out',
      },
      keyframes: {
        fadeIn: {
          '0%':   { opacity: '0', transform: 'translateY(4px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
    },
  },

  plugins: [
    require('daisyui'),
  ],

  daisyui: {
    themes: [
      {
        // ── Tema Light Procurement ─────────────────────────────────────────
        procurement_light: {
          'primary':         '#059669', // Emerald
          'primary-content': '#ffffff',
          'secondary':       '#d97706', // Gold/Amber
          'secondary-content': '#ffffff',
          'accent':          '#7c3aed', // Purple
          'accent-content':  '#ffffff',
          'neutral':         '#374151',
          'neutral-content': '#ffffff',
          'base-100':        '#ffffff',
          'base-200':        '#f3f4f6',
          'base-300':        '#e5e7eb',
          'base-content':    '#1f2937',
          'info':            '#0284c7',
          'success':         '#16a34a',
          'warning':         '#d97706',
          'error':           '#dc2626',  // Dark Red
        },
      },
      // Juga include tema bawaan untuk Filament
      'light',
      'dark',
    ],
    base: true,
    styled: true,
    utils: true,
    prefix: '',
    logs: false,
  },
}

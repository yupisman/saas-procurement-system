<!DOCTYPE html>
{{-- ==========================================================================
     FILE: resources/views/supplier-portal.blade.php
     PURPOSE: Entry point HTML untuk Vue SPA supplier portal.
              Satu halaman ini adalah shell untuk seluruh Vue app.
              Vue Router yang akan menangani navigasi selanjutnya.
     ========================================================================== --}}
<html lang="id" data-theme="procurement_light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    {{-- ── Capacitor: nonaktifkan zoom di mobile ──────────────────────────── --}}
    <meta name="format-detection" content="telephone=no">

    <title>Portal Supplier — Sistem Pengadaan</title>

    {{-- ── PWA / Mobile Meta ───────────────────────────────────────────────── --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Portal Supplier">

    {{-- ── CSRF Token (untuk Sanctum stateful) ────────────────────────────── --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- ── Preconnect Google Fonts ─────────────────────────────────────────── --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- ── Vite Assets (TailwindCSS + Vue) ────────────────────────────────── --}}
    @vite(['resources/css/app.css', 'resources/js/supplier-portal/main.js'])
</head>
<body class="bg-base-200 font-sans antialiased">

    {{-- Vue SPA mount point --}}
    <div id="app">
        {{-- Loading screen sebelum Vue mount (mencegah flash kosong) --}}
        <div class="min-h-screen flex items-center justify-center bg-emerald-700">
            <div class="text-center text-white">
                <div class="w-16 h-16 bg-white/20 rounded-2xl mx-auto flex items-center justify-center mb-4 animate-pulse">
                    <svg class="w-9 h-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="text-lg font-semibold">Portal Supplier</p>
                <p class="text-emerald-300 text-sm mt-1">Memuat aplikasi...</p>
            </div>
        </div>
    </div>

</body>
</html>

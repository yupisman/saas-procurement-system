# 📦 Sistem Pengadaan Supplier — Production Ready

> **Fullstack Procurement System** berbasis Laravel + Filament + Vue 3 + Capacitor
> Bahasa UI: Bahasa Indonesia

---

## 🌐 Gambaran Sistem

Sistem pengadaan terintegrasi yang mengelola seluruh alur dari **distribusi PR** hingga **closing** transaksi. PR dan PO berasal dari ERP sebagai **file PDF READ-ONLY** — sistem ini **tidak membuat** PR atau PO baru.

```
ERP (PR PDF) → Upload ke Sistem → Distribusi ke Supplier
     ↓
Supplier Buka Portal → Lihat PR → Submit Penawaran (multi-item + file)
     ↓
Purchasing Evaluasi → Perbandingan Otomatis → Approve Penawaran
     ↓
ERP (PO PDF) → Upload ke Sistem → Kirim ke Supplier
     ↓
Supplier Kirim Barang (foto) → Upload INVOICE + FAKTUR PAJAK
     ↓
Verifikasi → Closing → Sistem Selesai ✅
```

---

## 🏗️ Stack Teknologi

| Layer    | Teknologi                                           |
|----------|-----------------------------------------------------|
| Backend  | Laravel 11, PHP 8.2+                                |
| Admin    | Filament 3 + Livewire 3 (reactive admin panel)      |
| API Auth | Laravel Sanctum (token-based)                       |
| Frontend | Vue 3 (Composition API) + Pinia + Vue Router        |
| CSS      | TailwindCSS 3 + DaisyUI 4                           |
| Mobile   | Capacitor 6 (Android-ready)                         |
| Database | MySQL 8.0+                                          |
| Storage  | Laravel Storage (local / S3 compatible)             |

---

## 🗂️ Struktur Folder

```
/app
  /Models              ← Semua Eloquent Model (User, Supplier, PR, Quotation, dll)
  /Services
    ProcurementService.php  ← Core business logic (upload, distribusi, evaluasi, closing)
  /Repositories        ← (siap extend untuk repository pattern)
  /Actions             ← (siap extend untuk action pattern)
  /Policies            ← Gate policies per resource
  /Http
    /Controllers/Api   ← API endpoint untuk Vue portal dan Capacitor
    /Middleware        ← RoleMiddleware, SupplierActiveMiddleware
    /Requests          ← Form request validation
  /Filament
    /Resources         ← Filament CRUD resources (PR, Supplier, Quotation, dll)
    /Pages             ← Custom Filament pages
    /Widgets           ← Dashboard widgets (stats, chart, ranking, activity)
  /Livewire
    /Purchasing        ← QuotationComparison (reactive comparison table)

/database
  /migrations          ← Single-file migration lengkap semua tabel
  /seeders             ← Seeder: kategori, user, supplier contoh

/resources
  /views
    /livewire          ← Blade views untuk Livewire components
    supplier-portal.blade.php  ← SPA shell untuk Vue
  /js/supplier-portal  ← Vue 3 app
    /views             ← Halaman-halaman Vue (Login, Dashboard, PR, dll)
    /stores            ← Pinia stores (auth, procurement)
    /components        ← Komponen reusable (StatCard, Badge, Drawer, dll)
    /router            ← Vue Router config + auth guard
  /css
    app.css            ← Tailwind + DaisyUI + custom styles

/mobile
  capacitor.config.ts  ← Konfigurasi Capacitor
  src/
    capacitor-bridge.js  ← Bridge Vue ↔ Native (kamera, storage, network)
    dist/              ← Output build Vite (target Capacitor)
```

---

## 🚀 Instalasi & Deployment

### Prerequisites
- PHP 8.2+
- Composer 2.x
- Node.js 18+ & npm
- MySQL 8.0+

### Langkah Instalasi

```bash
# 1. Clone / upload project ke server
cd /var/www/html
# (upload file ZIP, lalu extract)

# 2. Install PHP dependencies
composer install --optimize-autoloader --no-dev

# 3. Install Node dependencies & build assets
npm install
npm run build

# 4. Konfigurasi environment
cp .env.example .env
php artisan key:generate

# 5. Edit .env sesuai server
nano .env
# Isi: APP_URL, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 6. Jalankan migrasi
php artisan migrate

# 7. Seed data awal (kategori, admin, contoh supplier)
php artisan db:seed

# 8. Link storage (untuk akses file publik)
php artisan storage:link

# 9. Set permission folder
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 10. Optimize untuk production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Akses Sistem

| Role          | URL                     | Email                        | Password         |
|---------------|-------------------------|------------------------------|------------------|
| Admin         | `/admin`                | `admin@perusahaan.com`       | `admin123!`      |
| Purchasing    | `/admin`                | `budi@perusahaan.com`        | `purchasing123!` |
| Supplier      | `/` atau `/portal`      | `supplier1@mbj.co.id`        | `supplier123!`   |

> ⚠️ **WAJIB** ganti semua password default setelah instalasi!

---

## 📱 Mobile (Capacitor / Android)

```bash
# Build Vue untuk mobile
npm run build:mobile

# Sync ke Capacitor
cd mobile
npx cap sync android

# Buka di Android Studio
npx cap open android

# Atau langsung run ke device
npx cap run android
```

### Konfigurasi API URL untuk Mobile

Di `.env`:
```
VITE_API_URL=https://domain-anda.com
```

Capacitor akan menggunakan URL ini untuk semua API call.

---

## 🔌 API Documentation

### Base URL
```
https://domain-anda.com/api/v1
```

### Authentication
Semua endpoint terproteksi membutuhkan header:
```
Authorization: Bearer {token}
```

### Endpoint

#### 🔐 Auth
| Method | Endpoint        | Deskripsi          |
|--------|-----------------|--------------------|
| POST   | `/auth/login`   | Login, dapatkan token |
| POST   | `/auth/logout`  | Logout (revoke token) |
| GET    | `/auth/me`      | Info user aktif    |

#### 📋 Purchase Request (Supplier READ-ONLY)
| Method | Endpoint                           | Deskripsi                  |
|--------|------------------------------------|----------------------------|
| GET    | `/purchase-requests`               | Daftar PR yang dikirim ke saya |
| GET    | `/purchase-requests/{id}`          | Detail PR                  |
| GET    | `/purchase-requests/{id}/download` | Download PDF PR             |

#### 📤 Quotation
| Method | Endpoint                       | Deskripsi                      |
|--------|--------------------------------|--------------------------------|
| POST   | `/quotations/pr/{prId}`        | Submit penawaran untuk PR ini  |
| GET    | `/quotations/my`               | Daftar penawaran saya          |
| GET    | `/quotations/{id}`             | Detail penawaran               |

**Body POST submit penawaran:**
```json
{
  "total_amount": 15000000,
  "delivery_days": 7,
  "valid_until": "2024-12-31",
  "quotation_number": "QUO/2024/001",
  "terms": "Pembayaran NET 30",
  "notes": "Stok ready",
  "items": [
    {
      "item_name": "Safety Helmet",
      "unit": "pcs",
      "quantity": 100,
      "unit_price": 150000,
      "specifications": "MSA V-Guard, warna kuning"
    }
  ]
}
```
*Plus file upload: `files[]` dan `file_categories[]`*

#### 🚚 Delivery
| Method | Endpoint                       | Deskripsi                  |
|--------|--------------------------------|----------------------------|
| GET    | `/deliveries`                  | Daftar pengiriman saya     |
| POST   | `/deliveries/po/{poId}`        | Submit data pengiriman + foto |

#### 🧾 Invoice & FAKTUR PAJAK
| Method | Endpoint                          | Deskripsi              |
|--------|-----------------------------------|------------------------|
| GET    | `/invoices`                       | Daftar INVOICE saya    |
| POST   | `/invoices/po/{poId}`             | Upload INVOICE         |
| POST   | `/invoices/{invoiceId}/faktur-pajak` | Upload FAKTUR PAJAK |

#### 🔔 Notifikasi
| Method | Endpoint                          | Deskripsi              |
|--------|-----------------------------------|------------------------|
| GET    | `/notifications`                  | Semua notifikasi       |
| PATCH  | `/notifications/{id}/read`        | Tandai satu dibaca     |
| POST   | `/notifications/mark-all-read`    | Tandai semua dibaca    |

#### 📊 Dashboard
| Method | Endpoint     | Deskripsi              |
|--------|--------------|------------------------|
| GET    | `/dashboard` | Statistik dashboard supplier |

---

## 🧩 Livewire Components

### QuotationComparison (`/app/Livewire/Purchasing/QuotationComparison.php`)

Tabel interaktif perbandingan penawaran supplier dengan fitur:

| Fitur                  | Implementasi                                  |
|------------------------|-----------------------------------------------|
| Sort kolom             | `wire:click="sortColumn('...')"` — tanpa reload |
| Filter status          | `wire:model.live="filterStatus"`              |
| Toggle kolom           | `wire:click="toggleColumn('...')"` (column picker) |
| Pilih untuk compare    | `wire:click="toggleSelect({id})"` (max 3)     |
| Side-by-side view      | Computed property `comparedQuotations`        |
| Approve langsung       | `wire:click="approveQuotation({id})"` + confirm |
| Auto evaluasi          | `wire:click="runEvaluation()"`                |
| Refresh otomatis       | Event listener `quotation-approved` → `$refresh` |

**Cara pakai di Blade:**
```blade
<livewire:purchasing.quotation-comparison :pr-id="$pr->id" />
```

---

## 🔐 Role & Permissions

| Aksi                           | Admin | Purchasing | Supplier |
|-------------------------------|:-----:|:----------:|:--------:|
| Login ke Filament admin panel | ✅    | ✅          | ❌       |
| Upload PR (PDF)                | ✅    | ✅          | ❌       |
| Distribusi PR ke supplier      | ✅    | ✅          | ❌       |
| Lihat semua PR                 | ✅    | ✅          | ❌       |
| Lihat PR yang dikirim ke saya  | ❌    | ❌          | ✅       |
| Submit penawaran               | ❌    | ❌          | ✅       |
| Evaluasi & approve penawaran   | ✅    | ✅          | ❌       |
| Upload PO (PDF)                | ✅    | ✅          | ❌       |
| Konfirmasi PO                  | ❌    | ❌          | ✅       |
| Upload bukti pengiriman (foto) | ❌    | ❌          | ✅       |
| Upload INVOICE                 | ❌    | ❌          | ✅       |
| Upload FAKTUR PAJAK            | ❌    | ❌          | ✅       |
| Verifikasi INVOICE             | ✅    | ✅          | ❌       |
| Closing PR                     | ✅    | ✅          | ❌       |
| Lihat audit log                | ✅    | ❌          | ❌       |
| Blacklist supplier             | ✅    | ❌          | ❌       |

---

## 📊 KPI Dashboard (Admin Filament)

Dashboard `/admin` menampilkan:
- **Stats Cards** — PR aktif, penawaran masuk, supplier aktif, nilai PO bulan ini
- **Doughnut Chart** — Distribusi status PR (real-time via polling)
- **Supplier Ranking** — Top 10 supplier berdasarkan skor composite:
  ```
  Skor = (Rating/5 × 40%) + (Win Rate × 30%) + (Total PO/50 × 30%)
  ```
- **Activity Feed** — Log aktivitas terbaru (refresh 15 detik)

---

## ⚙️ Konfigurasi Penting

### File Storage

```
storage/app/public/
  ├── pr/              ← PDF Purchase Request dari ERP
  ├── po/              ← PDF Purchase Order dari ERP
  ├── invoice/         ← PDF INVOICE dari supplier
  ├── faktur_pajak/    ← Scan FAKTUR PAJAK
  ├── delivery/        ← Foto bukti pengiriman
  └── quotations/      ← Dokumen lampiran penawaran
```

### Validasi File

| Jenis File     | Format Diizinkan    | Maks. Ukuran |
|----------------|---------------------|--------------|
| PR             | PDF                 | 10 MB        |
| PO             | PDF                 | 10 MB        |
| INVOICE        | PDF                 | 10 MB        |
| FAKTUR PAJAK   | PDF, JPG, PNG       | 10 MB        |
| Foto Pengiriman| JPG, JPEG, PNG      | 5 MB         |
| Lampiran Quotation | PDF, XLSX, JPG, PNG | 10 MB   |

---

## 🛡️ Security Checklist

- [x] Sanctum token authentication untuk semua API
- [x] Role-based middleware (`role:admin`, `role:purchasing`, `role:supplier`)
- [x] Supplier active check (blacklist tidak bisa akses)
- [x] File type validation (PDF only untuk PR/PO/INVOICE)
- [x] Supplier hanya bisa akses PR yang didistribusikan ke mereka
- [x] Audit log semua aksi (upload, distribusi, approval, download)
- [x] Soft delete untuk semua entitas utama
- [x] DB transactions untuk operasi multi-tabel
- [x] XSS protection via Laravel Blade escaping
- [ ] Rate limiting API (tambahkan di `routes/api.php`)
- [ ] HTTPS enforced via `.htaccess` / nginx config
- [ ] Ganti semua password default setelah instalasi!

---

## 🐛 Troubleshooting

**Storage link tidak berfungsi di shared hosting:**
```bash
php artisan storage:link
# Atau buat symlink manual:
ln -s /path/to/storage/app/public /path/to/public/storage
```

**PDF tidak bisa dibuka di Vue:**
- Pastikan token valid di localStorage
- Cek CORS di `.env`: `SANCTUM_STATEFUL_DOMAINS=domain-anda.com`

**Capacitor tidak bisa connect ke API:**
- Isi `VITE_API_URL` di `.env` dengan URL production (bukan localhost)
- Rebuild: `npm run build:mobile && cd mobile && npx cap sync`

---

## 📝 Changelog

| Versi | Tanggal    | Perubahan                              |
|-------|------------|----------------------------------------|
| 1.0.0 | 2024-04-01 | Initial release — sistem pengadaan lengkap |

---

**Dibuat dengan ❤️ untuk efisiensi pengadaan**

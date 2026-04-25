<?php
// =============================================================================
// FILE: database/migrations/2024_01_01_000001_create_procurement_tables.php
// PURPOSE: Satu file migrasi lengkap untuk seluruh schema sistem pengadaan.
//          Dipisah per tabel agar mudah di-rollback secara parsial.
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan semua migrasi procurement.
     * Urutan PENTING karena ada foreign key constraints.
     */
    public function up(): void
    {
        // ── 1. USERS (extend dari default Laravel) ──────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->enum('role', ['admin', 'purchasing', 'supplier'])->default('supplier')->after('phone');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            // Index untuk query by role (sering dipakai di middleware)
            $table->index('role');
            $table->index('is_active');
        });

        // ── 2. CATEGORIES (kategori barang/jasa) ────────────────────────────
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                   // Nama kategori
            $table->string('code', 20)->unique();          // Kode unik kategori
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ── 3. SUPPLIERS ─────────────────────────────────────────────────────
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
                  // Setiap supplier punya akun user untuk login portal
            $table->string('company_name', 200);           // Nama perusahaan supplier
            $table->string('npwp', 20)->nullable()->unique(); // NPWP supplier
            $table->string('pic_name', 100);               // Nama PIC / kontak
            $table->string('pic_phone', 20);               // HP PIC
            $table->text('address');                       // Alamat lengkap
            $table->string('city', 100);
            $table->string('province', 100)->nullable();
            $table->enum('status', ['aktif', 'nonaktif', 'blacklist'])->default('aktif');
            $table->text('blacklist_reason')->nullable();  // Alasan blacklist jika ada

            // ── Penilaian Supplier (untuk fitur ranking) ──────────────────
            $table->decimal('rating', 3, 2)->default(0.00); // Rating 0-5
            $table->integer('total_po')->default(0);        // Total PO yang pernah diterima
            $table->integer('total_quotation')->default(0); // Total penawaran yang disubmit
            $table->decimal('win_rate', 5, 2)->default(0); // % menang dari total penawaran

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index untuk performa query
            $table->index('status');
            $table->index('rating');
            $table->index('city');
        });

        // ── 4. SUPPLIER_CATEGORIES (many-to-many) ────────────────────────────
        Schema::create('supplier_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['supplier_id', 'category_id']); // Hindari duplikasi
        });

        // ── 5. PURCHASE REQUESTS (PR) ─────────────────────────────────────
        // PENTING: PR hanya berupa upload PDF dari ERP, TIDAK dibuat di sistem ini
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('pr_number', 50)->unique(); // Nomor PR dari ERP
            $table->string('title', 200);              // Judul / deskripsi PR
            $table->foreignId('category_id')->nullable()->constrained();
            $table->string('file_path', 500);          // Path file PDF PR
            $table->string('file_name', 255);          // Nama file asli
            $table->bigInteger('file_size')->default(0); // Ukuran file (bytes)

            // ── Status alur pengadaan ──────────────────────────────────────
            $table->enum('status', [
                'draft',          // Baru diupload, belum didistribusikan
                'didistribusi',   // Sudah dikirim ke supplier
                'penawaran',      // Dalam proses pengumpulan penawaran
                'evaluasi',       // Sedang dievaluasi purchasing
                'disetujui',      // Penawaran terpilih sudah disetujui
                'po_diterbitkan', // PO sudah diupload
                'pengiriman',     // Barang sedang dikirim
                'selesai',        // Transaksi selesai
                'dibatalkan',     // PR dibatalkan
            ])->default('draft');

            $table->date('deadline');                  // Batas waktu penawaran
            $table->text('notes')->nullable();         // Catatan internal
            $table->foreignId('created_by')->constrained('users'); // Siapa yang upload
            $table->foreignId('assigned_to')->nullable()->constrained('users');
                  // Purchasing staff yang bertanggung jawab

            // ── Metadata distribusi ───────────────────────────────────────
            $table->timestamp('distributed_at')->nullable(); // Waktu distribusi
            $table->timestamp('closed_at')->nullable();      // Waktu closing

            $table->timestamps();
            $table->softDeletes();

            // Index untuk filter dashboard
            $table->index('status');
            $table->index('deadline');
            $table->index('created_by');
        });

        // ── 6. PR_SUPPLIERS (distribusi PR ke supplier mana saja) ────────────
        Schema::create('pr_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['terkirim', 'dibuka', 'penawaran_dikirim', 'tidak_respon'])
                  ->default('terkirim');
            $table->timestamp('sent_at')->nullable();    // Kapan PR dikirim ke supplier
            $table->timestamp('opened_at')->nullable();  // Kapan supplier membuka PR
            $table->timestamps();

            $table->unique(['purchase_request_id', 'supplier_id']);
            $table->index('purchase_request_id');
        });

        // ── 7. QUOTATIONS (penawaran dari supplier) ───────────────────────────
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->string('quotation_number', 50)->nullable(); // Nomor penawaran supplier
            $table->decimal('total_amount', 15, 2);    // Total nilai penawaran (IDR)
            $table->integer('delivery_days');           // Estimasi hari pengiriman
            $table->date('valid_until');                // Masa berlaku penawaran
            $table->text('terms')->nullable();          // Syarat & ketentuan supplier
            $table->text('notes')->nullable();          // Catatan tambahan

            // ── Status penawaran ───────────────────────────────────────────
            $table->enum('status', [
                'submitted',  // Baru dikirim supplier
                'review',     // Sedang direview purchasing
                'selected',   // Dipilih / menang
                'rejected',   // Ditolak
                'revised',    // Diminta revisi
            ])->default('submitted');

            // ── Scoring untuk ranking ──────────────────────────────────────
            $table->decimal('score', 5, 2)->default(0); // Total skor evaluasi
            $table->boolean('is_best')->default(false); // Flag best quotation (auto)
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('purchase_request_id');
            $table->index('supplier_id');
            $table->index('status');
            $table->index('is_best');
        });

        // ── 8. QUOTATION FILES (lampiran penawaran, bisa multiple) ───────────
        Schema::create('quotation_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->onDelete('cascade');
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('file_type', 50);            // pdf, xlsx, jpg, dll
            $table->bigInteger('file_size')->default(0);
            $table->enum('category', [
                'penawaran_harga',  // Dokumen harga utama
                'spesifikasi',      // Spesifikasi teknis
                'sertifikat',       // Sertifikat / legalitas
                'lainnya',
            ])->default('penawaran_harga');
            $table->timestamps();
        });

        // ── 9. QUOTATION ITEMS (rincian item penawaran) ───────────────────────
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->onDelete('cascade');
            $table->string('item_name', 200);
            $table->string('unit', 50);                 // Satuan (pcs, kg, m, dll)
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 15, 2);       // Harga satuan
            $table->decimal('total_price', 15, 2);      // qty * unit_price
            $table->text('specifications')->nullable();  // Spek barang yang ditawarkan
            $table->timestamps();
        });

        // ── 10. APPROVALS (workflow persetujuan) ──────────────────────────────
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable'); // Bisa untuk PR, Quotation, PO
            $table->foreignId('approver_id')->constrained('users');
            $table->integer('level')->default(1);       // Level approval (1, 2, 3)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // Index manual dihapus karena morphs() sudah membuat index otomatis
            $table->index('approver_id');
            $table->index('status');
        });

        // ── 11. PURCHASE ORDERS (PO) ──────────────────────────────────────────
        // PENTING: PO hanya upload PDF dari ERP, TIDAK digenerate sistem ini
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained();
            $table->foreignId('quotation_id')->constrained(); // PO merujuk ke penawaran terpilih
            $table->foreignId('supplier_id')->constrained();
            $table->string('po_number', 50)->unique(); // Nomor PO dari ERP
            $table->string('file_path', 500);          // Path PDF PO dari ERP
            $table->string('file_name', 255);
            $table->bigInteger('file_size')->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->date('delivery_deadline');          // Batas waktu pengiriman

            $table->enum('status', [
                'diterbitkan',    // PO baru diupload
                'dikirim',        // PO dikirim ke supplier
                'dikonfirmasi',   // Supplier mengkonfirmasi
                'dalam_proses',   // Supplier sedang proses
                'dikirim_barang', // Barang sedang dikirim
                'diterima',       // Barang sudah diterima
                'selesai',        // PO selesai
            ])->default('diterbitkan');

            $table->timestamp('sent_to_supplier_at')->nullable();
            $table->timestamp('confirmed_by_supplier_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('supplier_id');
        });

        // ── 12. DELIVERIES (pengiriman barang) ────────────────────────────────
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->string('delivery_number', 100)->nullable(); // Nomor surat jalan
            $table->date('delivery_date');              // Tanggal pengiriman aktual
            $table->date('received_date')->nullable();  // Tanggal diterima gudang
            $table->string('carrier', 100)->nullable(); // Ekspedisi / kurir
            $table->string('tracking_number', 100)->nullable();

            $table->enum('status', [
                'dikirim',    // Barang sudah dikirim supplier
                'dalam_perjalanan',
                'diterima',   // Diterima gudang
                'sebagian',   // Diterima sebagian
                'bermasalah', // Ada masalah
            ])->default('dikirim');

            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // ── 13. DELIVERY PHOTOS (foto bukti pengiriman) ───────────────────────
        Schema::create('delivery_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->enum('type', ['packing', 'delivery', 'received', 'damaged'])->default('delivery');
            $table->text('caption')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        // ── 14. INVOICES ──────────────────────────────────────────────────────
        // PENTING: INVOICE = dokumen dari supplier, bukan dari sistem
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('delivery_id')->nullable()->constrained();
            $table->string('invoice_number', 100)->unique(); // Nomor INVOICE dari supplier
            $table->date('invoice_date');
            $table->date('due_date')->nullable();        // Jatuh tempo pembayaran
            $table->decimal('amount', 15, 2);           // Nilai INVOICE (sebelum pajak)
            $table->decimal('tax_amount', 15, 2)->default(0); // PPN
            $table->decimal('total_amount', 15, 2);     // Total termasuk pajak
            $table->string('file_path', 500);           // Path PDF INVOICE
            $table->string('file_name', 255);
            $table->bigInteger('file_size')->default(0);

            $table->enum('status', [
                'diterima',     // INVOICE diterima
                'diverifikasi', // Sedang diverifikasi
                'disetujui',    // Disetujui untuk pembayaran
                'dibayar',      // Sudah dibayar
                'ditolak',      // Ditolak (ada masalah)
            ])->default('diterima');

            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('due_date');
        });

        // ── 15. FAKTUR PAJAK ──────────────────────────────────────────────────
        // PENTING: FAKTUR PAJAK = dokumen pajak dari supplier
        Schema::create('faktur_pajak', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained();
            $table->string('nomor_faktur', 30)->unique(); // Nomor FAKTUR PAJAK (format DJP)
            $table->date('tanggal_faktur');
            $table->decimal('dpp', 15, 2);              // Dasar Pengenaan Pajak
            $table->decimal('ppn', 15, 2);              // PPN 11%
            $table->string('file_path', 500)->nullable(); // Scan/foto FAKTUR PAJAK
            $table->string('file_name', 255)->nullable();
            $table->bigInteger('file_size')->default(0);

            $table->enum('status', [
                'diterima',
                'diverifikasi',
                'valid',
                'tidak_valid',
            ])->default('diterima');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── 16. NOTIFICATIONS (notifikasi sistem) ─────────────────────────────
        Schema::create('procurement_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 100);               // Jenis notifikasi
            $table->string('title', 200);
            $table->text('message');
            $table->json('data')->nullable();           // Data tambahan (JSON)
            $table->morphs('notifiable');              // Bisa merujuk ke PR, PO, dll
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index('type');
        });

        // ── 17. AUDIT LOGS (semua aksi dicatat) ───────────────────────────────
        // Menggunakan spatie/activitylog, tabel ini adalah extension
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);             // create, update, delete, download, dll
            $table->string('module', 100);             // Modul: PR, PO, Quotation, dll
            $table->morphs('loggable');                // Record yang terpengaruh
            $table->text('description');               // Deskripsi aksi
            $table->json('old_values')->nullable();    // Nilai sebelum perubahan
            $table->json('new_values')->nullable();    // Nilai setelah perubahan
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            // Index untuk query audit trail yang cepat
            $table->index('user_id');
            $table->index('action');
            $table->index('module');
            // Index manual dihapus karena morphs() sudah membuat index otomatis
            $table->index('created_at');
        });

        // ── 18. SETTINGS (konfigurasi sistem) ─────────────────────────────────
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 50)->default('string'); // string, integer, boolean, json
            $table->string('group', 100)->default('general');
            $table->string('label', 200)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Rollback semua tabel procurement.
     * Urutan drop TERBALIK dari pembuatan (foreign key)
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('procurement_notifications');
        Schema::dropIfExists('faktur_pajak');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('delivery_photos');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotation_files');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('pr_suppliers');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('supplier_categories');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('categories');

        // Hapus kolom tambahan dari users
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['phone', 'role', 'is_active', 'last_login_at']);
            });
        }
    }
};

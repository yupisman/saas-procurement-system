<?php
// =============================================================================
// FILE: routes/api.php
// PURPOSE: Semua endpoint API untuk supplier portal (Vue) dan mobile (Capacitor)
// =============================================================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\NotificationController;

// ── Versi API ─────────────────────────────────────────────────────────────────
// Semua endpoint diawali dengan /api/v1/
Route::prefix('v1')->group(function () {

    // ── AUTH (publik, tidak perlu token) ─────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])
             ->middleware('throttle:5,1') // Limit login attempts: 5 per minute
             ->name('api.auth.login');
    });

    // ── ENDPOINT TERPROTEKSI (butuh Sanctum token) ────────────────────────────
    Route::middleware(['auth:sanctum', 'supplier.active', 'throttle:60,1'])->group(function () {

        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/auth/me',      [AuthController::class, 'me'])->name('api.auth.me');

        // ── PR - Supplier READ only ───────────────────────────────────────────
        Route::middleware('role:supplier')->prefix('purchase-requests')->group(function () {
            Route::get('/',           [PurchaseRequestController::class, 'index'])
                 ->name('api.pr.index');
            Route::get('/{purchaseRequest}', [PurchaseRequestController::class, 'show'])
                 ->name('api.pr.show');
            Route::get('/{purchaseRequest}/download', [PurchaseRequestController::class, 'download'])
                 ->name('api.pr.download');
        });

        // ── QUOTATIONS ────────────────────────────────────────────────────────
        Route::middleware('role:supplier')->prefix('quotations')->group(function () {
            // Submit penawaran untuk PR
            Route::post('/pr/{purchaseRequest}', [QuotationController::class, 'store'])
                 ->name('api.quotation.store');
            // Daftar penawaran saya
            Route::get('/my', [QuotationController::class, 'myQuotations'])
                 ->name('api.quotation.my');
            // Detail penawaran
            Route::get('/{quotation}', [QuotationController::class, 'show'])
                 ->name('api.quotation.show');
        });

        // ── DELIVERIES ────────────────────────────────────────────────────────
        Route::middleware('role:supplier')->prefix('deliveries')->group(function () {
            Route::get('/',                         [DeliveryController::class, 'index'])
                 ->name('api.delivery.index');
            Route::post('/po/{purchaseOrder}', [DeliveryController::class, 'store'])
                 ->name('api.delivery.store');
        });

        // ── INVOICES & FAKTUR PAJAK ───────────────────────────────────────────
        Route::middleware('role:supplier')->prefix('invoices')->group(function () {
            Route::get('/',                        [InvoiceController::class, 'index'])
                 ->name('api.invoice.index');
            Route::post('/po/{purchaseOrder}', [InvoiceController::class, 'store'])
                 ->name('api.invoice.store');
            Route::post('/{invoice}/faktur-pajak', [InvoiceController::class, 'storeFakturPajak'])
                 ->name('api.faktur.store');
        });

        // ── NOTIFICATIONS ─────────────────────────────────────────────────────
        Route::prefix('notifications')->group(function () {
            Route::get('/',                                [NotificationController::class, 'index'])
                 ->name('api.notification.index');
            Route::patch('/{notification}/read',           [NotificationController::class, 'markRead'])
                 ->name('api.notification.read');
            Route::post('/mark-all-read',                  [NotificationController::class, 'markAllRead'])
                 ->name('api.notification.readAll');
        });

        // ── DASHBOARD STATS (supplier) ─────────────────────────────────────
        Route::middleware('role:supplier')->get('/dashboard', function (\Illuminate\Http\Request $req) {
            $supplier   = $req->user()->supplier;
            $prCount    = \App\Models\PrSupplier::where('supplier_id', $supplier->id)->count();
            $quotations = \App\Models\Quotation::where('supplier_id', $supplier->id);
            $poCount    = \App\Models\PurchaseOrder::where('supplier_id', $supplier->id)->count();

            return response()->json([
                'pr_diterima'      => $prCount,
                'penawaran_dikirim'=> $quotations->count(),
                'penawaran_menang' => $quotations->clone()->where('status', 'selected')->count(),
                'total_po'         => $poCount,
                'win_rate'         => $supplier->win_rate,
                'rating'           => $supplier->rating,
                'notif_unread'     => \App\Models\ProcurementNotification::where('user_id', $req->user()->id)
                                         ->where('is_read', false)->count(),
            ]);
        })->name('api.supplier.dashboard');
    });
});

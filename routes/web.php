<?php
// =============================================================================
// FILE: routes/web.php
// PURPOSE: Web routes untuk Filament admin dan supplier portal Vue SPA
// =============================================================================
use Illuminate\Support\Facades\Route;

// ── Redirect root ke admin (jika login) atau supplier portal ─────────────────
Route::get('/', function () {
    if (auth()->check()) {
        if (in_array(auth()->user()->role, ['admin', 'purchasing'])) {
            return redirect('/admin');
        }
    }
    // Supplier portal: render Vue SPA
    return view('supplier-portal');
});

// ── Supplier Portal Vue SPA ───────────────────────────────────────────────────
// Semua route /portal/* diarahkan ke Vue (Vue Router yang handle)
Route::get('/portal/{any?}', function () {
    return view('supplier-portal');
})->where('any', '.*');

// ── Protected file download routes (PR & PO PDF) ─────────────────────────────
// Hanya bisa diakses oleh user yang sudah login dengan hak akses
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/files/pr/{id}', function ($id) {
        $pr = \App\Models\PurchaseRequest::findOrFail($id);

        // Cek akses: admin/purchasing bisa semua, supplier hanya yang pernah dapat distribusi
        $user = auth()->user();
        if ($user->isSupplier()) {
            $hasAccess = \App\Models\PrSupplier::where('purchase_request_id', $id)
                ->where('supplier_id', $user->supplier->id)->exists();
            abort_if(!$hasAccess, 403);
        }

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($pr->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        \App\Models\AuditLog::record('download_pr_web', 'PR', $pr, "Download PDF PR #{$pr->pr_number}");
        return \Illuminate\Support\Facades\Storage::disk('public')->download($pr->file_path, "PR_{$pr->pr_number}.pdf");
    })->name('pr.download');

    Route::get('/files/po/{id}', function ($id) {
        $po   = \App\Models\PurchaseOrder::findOrFail($id);
        $user = auth()->user();

        if ($user->isSupplier() && $po->supplier_id !== $user->supplier->id) {
            abort(403);
        }

        \App\Models\AuditLog::record('download_po_web', 'PO', $po, "Download PDF PO #{$po->po_number}");
        return \Illuminate\Support\Facades\Storage::disk('public')->download($po->file_path, "PO_{$po->po_number}.pdf");
    })->name('po.download');
});

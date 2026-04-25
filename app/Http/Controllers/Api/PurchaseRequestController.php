<?php
// =============================================================================
// FILE: app/Http/Controllers/Api/PurchaseRequestController.php
// PURPOSE: API endpoint PR untuk supplier portal.
//          Supplier HANYA bisa READ PR yang didistribusikan ke mereka.
// =============================================================================
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\PrSupplier;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PurchaseRequestController extends Controller
{
    /**
     * Daftar PR yang didistribusikan ke supplier yang sedang login.
     * Hanya tampilkan PR yang memang dikirim ke supplier ini.
     */
    public function index(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;

        if (!$supplier) {
            return response()->json(['message' => 'Akun bukan supplier.'], 403);
        }

        $query = PurchaseRequest::whereHas('prSuppliers', function ($q) use ($supplier) {
                    $q->where('supplier_id', $supplier->id);
                 })
                 ->with(['category', 'prSuppliers' => function ($q) use ($supplier) {
                    $q->where('supplier_id', $supplier->id);
                 }])
                 ->withCount('quotations');

        // Filter by status jika diminta
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Cari berdasarkan nomor PR atau judul
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('pr_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $prs = $query->orderByDesc('created_at')->paginate(15);

        // Tandai sebagai 'dibuka' jika baru pertama kali dilihat
        foreach ($prs as $pr) {
            $prSupplier = $pr->prSuppliers->first();
            if ($prSupplier && $prSupplier->status === 'terkirim') {
                $prSupplier->update([
                    'status'    => 'dibuka',
                    'opened_at' => now(),
                ]);
            }
        }

        return response()->json($prs);
    }

    /**
     * Detail satu PR untuk supplier.
     * Catat aksi buka dokumen untuk audit trail.
     */
    public function show(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $supplier = $request->user()->supplier;

        // Pastikan PR ini memang dikirim ke supplier ini
        $prSupplier = PrSupplier::where('purchase_request_id', $purchaseRequest->id)
                                 ->where('supplier_id', $supplier->id)
                                 ->first();

        if (!$prSupplier) {
            return response()->json(['message' => 'Tidak ditemukan atau akses ditolak.'], 404);
        }

        // Tandai sebagai dibuka
        if ($prSupplier->status === 'terkirim') {
            $prSupplier->update(['status' => 'dibuka', 'opened_at' => now()]);
        }

        // Catat di audit log bahwa supplier membuka PR
        AuditLog::record(
            action: 'view_pr',
            module: 'PR',
            loggable: $purchaseRequest,
            description: "Supplier #{$supplier->id} ({$supplier->company_name}) membuka PR #{$purchaseRequest->pr_number}"
        );

        // Load relasi yang diperlukan untuk detail view
        $purchaseRequest->load(['category', 'quotations' => function ($q) use ($supplier) {
            $q->where('supplier_id', $supplier->id)->with('items', 'files');
        }]);

        // Cek apakah supplier sudah pernah submit penawaran
        $myQuotation = $purchaseRequest->quotations->first();

        return response()->json([
            'purchase_request' => $purchaseRequest,
            'my_quotation'     => $myQuotation,
            'can_submit'       => !$myQuotation && $purchaseRequest->deadline->isFuture()
                                  && in_array($purchaseRequest->status, ['didistribusi', 'penawaran']),
        ]);
    }

    /**
     * Download file PDF PR (dengan autentikasi).
     * Hanya supplier yang menerima distribusi yang bisa download.
     */
    public function download(Request $request, PurchaseRequest $purchaseRequest)
    {
        $supplier = $request->user()->supplier;

        // Validasi akses
        $hasAccess = PrSupplier::where('purchase_request_id', $purchaseRequest->id)
                                ->where('supplier_id', $supplier->id)
                                ->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // Cek file ada
        if (!Storage::disk('public')->exists($purchaseRequest->file_path)) {
            return response()->json(['message' => 'File tidak ditemukan.'], 404);
        }

        // Catat download di audit
        AuditLog::record(
            action: 'download_pr',
            module: 'PR',
            loggable: $purchaseRequest,
            description: "Supplier #{$supplier->id} download PDF PR #{$purchaseRequest->pr_number}"
        );

        return Storage::disk('public')->download(
            $purchaseRequest->file_path,
            "PR_{$purchaseRequest->pr_number}.pdf"
        );
    }
}

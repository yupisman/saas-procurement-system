<?php
// =============================================================================
// FILE: app/Http/Controllers/Api/QuotationController.php
// PURPOSE: API endpoint untuk supplier submit dan monitoring penawaran.
// =============================================================================
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\QuotationFile;
use App\Models\QuotationItem;
use App\Models\PrSupplier;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuotationController extends Controller
{
    /**
     * Submit penawaran untuk sebuah PR.
     * Supplier hanya boleh submit sekali per PR.
     * Mendukung multiple file lampiran.
     */
    public function store(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $supplier = $request->user()->supplier;

        // ── Validasi akses ────────────────────────────────────────────────────
        $prSupplier = PrSupplier::where('purchase_request_id', $purchaseRequest->id)
                                 ->where('supplier_id', $supplier->id)
                                 ->first();

        if (!$prSupplier) {
            return response()->json(['message' => 'PR tidak ditemukan atau tidak ditujukan ke Anda.'], 403);
        }

        if ($purchaseRequest->deadline->isPast()) {
            return response()->json(['message' => 'Batas waktu penawaran sudah lewat.'], 422);
        }

        if (!in_array($purchaseRequest->status, ['didistribusi', 'penawaran'])) {
            return response()->json(['message' => 'PR tidak dalam status penerimaan penawaran.'], 422);
        }

        // Cek sudah pernah submit
        $existing = Quotation::where('purchase_request_id', $purchaseRequest->id)
                              ->where('supplier_id', $supplier->id)
                              ->whereNotIn('status', ['rejected'])
                              ->first();

        if ($existing) {
            return response()->json([
                'message'   => 'Anda sudah pernah mengirimkan penawaran untuk PR ini.',
                'quotation' => $existing->load('items', 'files'),
            ], 422);
        }

        // ── Validasi input ────────────────────────────────────────────────────
        $validated = $request->validate([
            'quotation_number'    => 'nullable|string|max:50',
            'total_amount'        => 'required|numeric|min:1',
            'delivery_days'       => 'required|integer|min:1|max:365',
            'valid_until'         => 'required|date|after:today',
            'terms'               => 'nullable|string|max:2000',
            'notes'               => 'nullable|string|max:1000',
            'items'               => 'required|array|min:1',
            'items.*.item_name'   => 'required|string|max:200',
            'items.*.unit'        => 'required|string|max:50',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.specifications' => 'nullable|string',
            'files'               => 'nullable|array|max:5',
            'files.*'             => 'file|mimes:pdf,jpg,jpeg,png,xlsx,xls|max:10240',
            'file_categories'     => 'nullable|array',
        ]);

        return DB::transaction(function () use ($validated, $request, $purchaseRequest, $supplier, $prSupplier) {

            // ── Buat Quotation ────────────────────────────────────────────────
            $quotation = Quotation::create([
                'purchase_request_id' => $purchaseRequest->id,
                'supplier_id'         => $supplier->id,
                'quotation_number'    => $validated['quotation_number'] ?? null,
                'total_amount'        => $validated['total_amount'],
                'delivery_days'       => $validated['delivery_days'],
                'valid_until'         => $validated['valid_until'],
                'terms'               => $validated['terms'] ?? null,
                'notes'               => $validated['notes'] ?? null,
                'status'              => 'submitted',
            ]);

            // ── Simpan Item Penawaran ─────────────────────────────────────────
            foreach ($validated['items'] as $item) {
                QuotationItem::create([
                    'quotation_id'   => $quotation->id,
                    'item_name'      => $item['item_name'],
                    'unit'           => $item['unit'],
                    'quantity'       => $item['quantity'],
                    'unit_price'     => $item['unit_price'],
                    'total_price'    => $item['quantity'] * $item['unit_price'],
                    'specifications' => $item['specifications'] ?? null,
                ]);
            }

            // ── Upload File Lampiran ──────────────────────────────────────────
            if ($request->hasFile('files')) {
                $categories = $request->file_categories ?? [];
                foreach ($request->file('files') as $index => $file) {
                    $path = $file->store("quotations/{$quotation->id}", 'public');
                    QuotationFile::create([
                        'quotation_id' => $quotation->id,
                        'file_path'    => $path,
                        'file_name'    => $file->getClientOriginalName(),
                        'file_type'    => $file->getClientOriginalExtension(),
                        'file_size'    => $file->getSize(),
                        'category'     => $categories[$index] ?? 'penawaran_harga',
                    ]);
                }
            }

            // ── Update status PR dan pivot ────────────────────────────────────
            $purchaseRequest->update(['status' => 'penawaran']);
            $prSupplier->update(['status' => 'penawaran_dikirim']);

            // Update statistik supplier
            $supplier->increment('total_quotation');

            // Audit log
            AuditLog::record(
                action: 'submit_quotation',
                module: 'Quotation',
                loggable: $quotation,
                description: "Supplier {$supplier->company_name} submit penawaran untuk PR #{$purchaseRequest->pr_number}. Nilai: Rp " . number_format($quotation->total_amount),
                newValues: ['total_amount' => $quotation->total_amount, 'delivery_days' => $quotation->delivery_days]
            );

            return response()->json([
                'message'   => 'Penawaran berhasil dikirim.',
                'quotation' => $quotation->load('items', 'files'),
            ], 201);
        });
    }

    /**
     * Daftar penawaran milik supplier yang sedang login.
     * Untuk halaman monitoring status penawaran.
     */
    public function myQuotations(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;

        $quotations = Quotation::where('supplier_id', $supplier->id)
            ->with(['purchaseRequest:id,pr_number,title,status,deadline', 'items'])
            ->withCount('files')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($quotations);
    }

    /**
     * Detail satu penawaran milik supplier.
     */
    public function show(Request $request, Quotation $quotation): JsonResponse
    {
        $supplier = $request->user()->supplier;

        // Pastikan penawaran ini milik supplier yang sedang login
        if ($quotation->supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $quotation->load(['purchaseRequest', 'items', 'files']);

        return response()->json(['quotation' => $quotation]);
    }
}

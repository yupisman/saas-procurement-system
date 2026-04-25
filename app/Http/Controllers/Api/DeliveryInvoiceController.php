<?php
// =============================================================================
// FILE: app/Http/Controllers/Api/DeliveryController.php
// PURPOSE: Supplier mengupload bukti pengiriman (foto) via API (mobile-ready)
// =============================================================================
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Delivery;
use App\Models\DeliveryPhoto;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Supplier submit info pengiriman dan upload foto.
     * Mendukung upload foto dari kamera (Capacitor) atau file picker.
     */
    public function store(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $supplier = $request->user()->supplier;

        // Validasi PO milik supplier ini
        if ($purchaseOrder->supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        if (!in_array($purchaseOrder->status, ['dikirim', 'dikonfirmasi', 'dalam_proses'])) {
            return response()->json(['message' => 'Status PO tidak memungkinkan input pengiriman.'], 422);
        }

        $validated = $request->validate([
            'delivery_number'  => 'nullable|string|max:100',
            'delivery_date'    => 'required|date',
            'carrier'          => 'nullable|string|max:100',
            'tracking_number'  => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:1000',
            'photos'           => 'nullable|array|max:10',
            'photos.*'         => 'image|mimes:jpg,jpeg,png|max:5120', // max 5MB per foto
            'photo_types'      => 'nullable|array',
            'photo_captions'   => 'nullable|array',
        ]);

        return DB::transaction(function () use ($validated, $request, $purchaseOrder, $supplier) {

            $delivery = Delivery::create([
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id'       => $supplier->id,
                'delivery_number'   => $validated['delivery_number'] ?? null,
                'delivery_date'     => $validated['delivery_date'],
                'carrier'           => $validated['carrier'] ?? null,
                'tracking_number'   => $validated['tracking_number'] ?? null,
                'notes'             => $validated['notes'] ?? null,
                'status'            => 'dikirim',
            ]);

            // Upload foto-foto pengiriman
            if ($request->hasFile('photos')) {
                $types    = $request->photo_types ?? [];
                $captions = $request->photo_captions ?? [];

                foreach ($request->file('photos') as $index => $photo) {
                    $path = $photo->store("delivery/{$delivery->id}", 'public');
                    DeliveryPhoto::create([
                        'delivery_id' => $delivery->id,
                        'file_path'   => $path,
                        'file_name'   => $photo->getClientOriginalName(),
                        'type'        => $types[$index] ?? 'delivery',
                        'caption'     => $captions[$index] ?? null,
                        'uploaded_by' => auth()->id(),
                    ]);
                }
            }

            // Update status PO
            $purchaseOrder->update(['status' => 'dikirim_barang']);
            $purchaseOrder->purchaseRequest->update(['status' => 'pengiriman']);

            AuditLog::record(
                action: 'submit_delivery',
                module: 'Delivery',
                loggable: $delivery,
                description: "Supplier {$supplier->company_name} submit pengiriman untuk PO #{$purchaseOrder->po_number}"
            );

            return response()->json([
                'message'  => 'Data pengiriman berhasil dikirim.',
                'delivery' => $delivery->load('photos'),
            ], 201);
        });
    }

    /**
     * Daftar pengiriman milik supplier.
     */
    public function index(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;

        $deliveries = Delivery::where('supplier_id', $supplier->id)
            ->with(['purchaseOrder:id,po_number,status', 'photos'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($deliveries);
    }
}


// =============================================================================
// FILE: app/Http/Controllers/Api/InvoiceController.php
// PURPOSE: Supplier upload INVOICE dan FAKTUR PAJAK via API
// =============================================================================
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\FakturPajak;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Supplier upload INVOICE.
     * INVOICE adalah dokumen dari supplier ke pembeli,
     * sistem hanya menerima dan menyimpan, tidak men-generate.
     */
    public function store(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $supplier = $request->user()->supplier;

        if ($purchaseOrder->supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $validated = $request->validate([
            'invoice_number'  => 'required|string|max:100|unique:invoices,invoice_number',
            'invoice_date'    => 'required|date',
            'due_date'        => 'nullable|date|after_or_equal:invoice_date',
            'amount'          => 'required|numeric|min:1',
            'tax_amount'      => 'nullable|numeric|min:0',
            'delivery_id'     => 'nullable|exists:deliveries,id',
            'notes'           => 'nullable|string|max:1000',
            'file'            => 'required|file|mimes:pdf|max:10240', // PDF only
        ]);

        return DB::transaction(function () use ($validated, $request, $purchaseOrder, $supplier) {

            // Upload file INVOICE
            $file    = $request->file('file');
            $path    = $file->store('invoice', 'public');
            $taxAmt  = $validated['tax_amount'] ?? 0;
            $total   = $validated['amount'] + $taxAmt;

            $invoice = Invoice::create([
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id'       => $supplier->id,
                'delivery_id'       => $validated['delivery_id'] ?? null,
                'invoice_number'    => $validated['invoice_number'],
                'invoice_date'      => $validated['invoice_date'],
                'due_date'          => $validated['due_date'] ?? null,
                'amount'            => $validated['amount'],
                'tax_amount'        => $taxAmt,
                'total_amount'      => $total,
                'file_path'         => $path,
                'file_name'         => $file->getClientOriginalName(),
                'file_size'         => $file->getSize(),
                'notes'             => $validated['notes'] ?? null,
                'status'            => 'diterima',
            ]);

            AuditLog::record(
                action: 'upload_invoice',
                module: 'Invoice',
                loggable: $invoice,
                description: "Supplier {$supplier->company_name} upload INVOICE #{$invoice->invoice_number} untuk PO #{$purchaseOrder->po_number}. Total: Rp " . number_format($total)
            );

            return response()->json([
                'message' => 'INVOICE berhasil diupload.',
                'invoice' => $invoice,
            ], 201);
        });
    }

    /**
     * Upload FAKTUR PAJAK untuk INVOICE yang sudah ada.
     */
    public function storeFakturPajak(Request $request, Invoice $invoice): JsonResponse
    {
        $supplier = $request->user()->supplier;

        if ($invoice->supplier_id !== $supplier->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // Cek belum ada FAKTUR PAJAK
        if ($invoice->fakturPajak) {
            return response()->json(['message' => 'FAKTUR PAJAK sudah pernah diupload untuk INVOICE ini.'], 422);
        }

        $validated = $request->validate([
            'nomor_faktur'    => 'required|string|max:30|unique:faktur_pajak,nomor_faktur',
            'tanggal_faktur'  => 'required|date',
            'dpp'             => 'required|numeric|min:1',
            'ppn'             => 'required|numeric|min:0',
            'notes'           => 'nullable|string|max:500',
            'file'            => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $path = null;
        $fileName = null;
        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $path     = $file->store('faktur_pajak', 'public');
            $fileName = $file->getClientOriginalName();
        }

        $faktur = FakturPajak::create([
            'invoice_id'      => $invoice->id,
            'supplier_id'     => $supplier->id,
            'nomor_faktur'    => $validated['nomor_faktur'],
            'tanggal_faktur'  => $validated['tanggal_faktur'],
            'dpp'             => $validated['dpp'],
            'ppn'             => $validated['ppn'],
            'file_path'       => $path,
            'file_name'       => $fileName,
            'file_size'       => $request->hasFile('file') ? $request->file('file')->getSize() : 0,
            'notes'           => $validated['notes'] ?? null,
            'status'          => 'diterima',
        ]);

        AuditLog::record(
            action: 'upload_faktur_pajak',
            module: 'FakturPajak',
            loggable: $faktur,
            description: "Supplier {$supplier->company_name} upload FAKTUR PAJAK #{$faktur->nomor_faktur} untuk INVOICE #{$invoice->invoice_number}"
        );

        return response()->json([
            'message'       => 'FAKTUR PAJAK berhasil diupload.',
            'faktur_pajak'  => $faktur,
        ], 201);
    }

    /**
     * Daftar INVOICE milik supplier dengan status.
     */
    public function index(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;

        $invoices = Invoice::where('supplier_id', $supplier->id)
            ->with(['purchaseOrder:id,po_number', 'fakturPajak'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($invoices);
    }
}


// =============================================================================
// FILE: app/Http/Controllers/Api/NotificationController.php
// PURPOSE: Endpoint notifikasi untuk supplier portal
// =============================================================================
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcurementNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Ambil semua notifikasi user yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = ProcurementNotification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $unreadCount = ProcurementNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * Tandai notifikasi sebagai sudah dibaca.
     */
    public function markRead(Request $request, ProcurementNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        $notification->markAsRead();
        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    /**
     * Tandai semua notifikasi sebagai sudah dibaca.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        ProcurementNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi sudah ditandai dibaca.']);
    }
}

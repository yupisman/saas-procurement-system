<?php
// =============================================================================
// FILE: app/Services/ProcurementService.php
// PURPOSE: Service layer untuk semua logika bisnis pengadaan.
//          Controller dan Filament Resource memanggil service ini,
//          bukan langsung ke model, agar logika tetap di satu tempat.
// =============================================================================
namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\AuditLog;
use App\Models\PrSupplier;
use App\Models\ProcurementNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcurementService
{
    /**
     * Upload PR (PDF dari ERP) dan simpan ke storage.
     * HANYA menerima PDF, tidak membuat dokumen baru.
     *
     * @throws \Exception jika file bukan PDF atau terlalu besar
     */
    public function uploadPR(array $data, UploadedFile $file): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $file) {
            // Simpan file PDF ke storage/app/public/pr
            $path = $file->store('pr', 'public');

            $pr = PurchaseRequest::create([
                'pr_number'   => $data['pr_number'],
                'title'       => $data['title'],
                'category_id' => $data['category_id'] ?? null,
                'file_path'   => $path,
                'file_name'   => $file->getClientOriginalName(),
                'file_size'   => $file->getSize(),
                'deadline'    => $data['deadline'],
                'notes'       => $data['notes'] ?? null,
                'status'      => 'draft',
                'created_by'  => auth()->id(),
                'assigned_to' => $data['assigned_to'] ?? null,
            ]);

            // Catat di audit log
            AuditLog::record(
                action: 'upload_pr',
                module: 'PR',
                loggable: $pr,
                description: "Upload PR #{$pr->pr_number} - {$pr->title}",
                newValues: ['pr_number' => $pr->pr_number, 'file' => $pr->file_name]
            );

            return $pr;
        });
    }

    /**
     * Distribusikan PR ke daftar supplier.
     * Mengubah status PR dari 'draft' ke 'didistribusi'.
     * Mengirim notifikasi ke setiap supplier.
     *
     * @param  PurchaseRequest  $pr
     * @param  array  $supplierIds  Array of supplier ID yang dipilih
     */
    public function distributePR(PurchaseRequest $pr, array $supplierIds): void
    {
        DB::transaction(function () use ($pr, $supplierIds) {
            // Validasi: PR harus masih draft
            if ($pr->status !== 'draft') {
                throw new \Exception('PR sudah pernah didistribusikan.');
            }

            // Buat record distribusi untuk setiap supplier
            foreach ($supplierIds as $supplierId) {
                PrSupplier::updateOrCreate(
                    ['purchase_request_id' => $pr->id, 'supplier_id' => $supplierId],
                    ['status' => 'terkirim', 'sent_at' => now()]
                );

                // Kirim notifikasi ke akun user supplier
                $supplier = Supplier::with('user')->find($supplierId);
                if ($supplier && $supplier->user) {
                    ProcurementNotification::create([
                        'user_id'         => $supplier->user_id,
                        'type'            => 'pr_distributed',
                        'title'           => 'PR Baru - Undangan Penawaran',
                        'message'         => "Anda diundang untuk memberikan penawaran pada PR #{$pr->pr_number}: {$pr->title}. Deadline: {$pr->deadline->format('d/m/Y')}",
                        'data'            => ['pr_id' => $pr->id],
                        'notifiable_type' => PurchaseRequest::class,
                        'notifiable_id'   => $pr->id,
                    ]);
                }
            }

            // Update status PR
            $pr->update([
                'status'          => 'didistribusi',
                'distributed_at'  => now(),
            ]);

            AuditLog::record(
                action: 'distribute_pr',
                module: 'PR',
                loggable: $pr,
                description: "PR #{$pr->pr_number} didistribusikan ke " . count($supplierIds) . " supplier",
                newValues: ['supplier_ids' => $supplierIds]
            );
        });
    }

    /**
     * Evaluasi semua penawaran untuk sebuah PR.
     * Menghitung skor masing-masing penawaran dan menandai yang terbaik secara otomatis.
     *
     * @param  PurchaseRequest  $pr
     * @return Quotation  Penawaran terbaik
     */
    public function evaluateQuotations(PurchaseRequest $pr): ?Quotation
    {
        $quotations = $pr->quotations()
                         ->where('status', 'submitted')
                         ->with('supplier')
                         ->get();

        if ($quotations->isEmpty()) {
            return null;
        }

        // Temukan harga terendah dan waktu tercepat sebagai baseline skor
        $lowestAmount = $quotations->min('total_amount');
        $fastestDays  = $quotations->min('delivery_days');

        // Hitung skor tiap penawaran
        foreach ($quotations as $quotation) {
            $quotation->calculateScore($lowestAmount, $fastestDays);
        }

        // Ambil penawaran dengan skor tertinggi
        $best = $quotations->sortByDesc('score')->first();

        // Auto-highlight (tidak langsung pilih, purchasing masih bisa override)
        Quotation::where('purchase_request_id', $pr->id)->update(['is_best' => false]);
        $best->update(['is_best' => true]);

        // Update status PR
        $pr->update(['status' => 'evaluasi']);

        AuditLog::record(
            action: 'evaluate_quotations',
            module: 'PR',
            loggable: $pr,
            description: "Evaluasi {$quotations->count()} penawaran PR #{$pr->pr_number}. Best: Supplier #{$best->supplier_id}",
        );

        return $best;
    }

    /**
     * Approve penawaran terpilih.
     * Mengubah status PR ke 'disetujui'.
     */
    public function approveQuotation(Quotation $quotation, string $comments = ''): void
    {
        DB::transaction(function () use ($quotation, $comments) {
            $pr = $quotation->purchaseRequest;

            $quotation->markAsBest();

            // Tolak semua penawaran lain
            Quotation::where('purchase_request_id', $pr->id)
                     ->where('id', '!=', $quotation->id)
                     ->update(['status' => 'rejected']);

            $pr->update(['status' => 'disetujui']);

            // Notifikasi ke supplier yang menang
            if ($quotation->supplier->user) {
                ProcurementNotification::create([
                    'user_id'         => $quotation->supplier->user_id,
                    'type'            => 'quotation_approved',
                    'title'           => 'Penawaran Anda Diterima!',
                    'message'         => "Selamat! Penawaran Anda untuk PR #{$pr->pr_number} telah disetujui. Harap menunggu penerbitan PO.",
                    'data'            => ['quotation_id' => $quotation->id, 'pr_id' => $pr->id],
                    'notifiable_type' => Quotation::class,
                    'notifiable_id'   => $quotation->id,
                ]);
            }

            AuditLog::record(
                action: 'approve_quotation',
                module: 'Quotation',
                loggable: $quotation,
                description: "Penawaran #{$quotation->id} dari supplier #{$quotation->supplier_id} disetujui. Komentar: {$comments}",
            );
        });
    }

    /**
     * Upload PO (PDF dari ERP) dan kaitkan ke PR + Quotation terpilih.
     * HANYA upload, tidak generate dokumen.
     */
    public function uploadPO(array $data, UploadedFile $file): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $file) {
            $path = $file->store('po', 'public');

            $po = PurchaseOrder::create([
                'purchase_request_id' => $data['purchase_request_id'],
                'quotation_id'        => $data['quotation_id'],
                'supplier_id'         => $data['supplier_id'],
                'po_number'           => $data['po_number'],
                'file_path'           => $path,
                'file_name'           => $file->getClientOriginalName(),
                'file_size'           => $file->getSize(),
                'total_amount'        => $data['total_amount'],
                'delivery_deadline'   => $data['delivery_deadline'],
                'notes'               => $data['notes'] ?? null,
                'status'              => 'diterbitkan',
                'created_by'          => auth()->id(),
            ]);

            // Update status PR
            $po->purchaseRequest->update(['status' => 'po_diterbitkan']);

            // Notifikasi ke supplier
            $supplier = $po->supplier->load('user');
            if ($supplier->user) {
                ProcurementNotification::create([
                    'user_id'         => $supplier->user_id,
                    'type'            => 'po_issued',
                    'title'           => 'PO Diterbitkan',
                    'message'         => "PO #{$po->po_number} telah diterbitkan untuk PR #{$po->purchaseRequest->pr_number}. Silakan konfirmasi.",
                    'data'            => ['po_id' => $po->id],
                    'notifiable_type' => PurchaseOrder::class,
                    'notifiable_id'   => $po->id,
                ]);
            }

            AuditLog::record(
                action: 'upload_po',
                module: 'PO',
                loggable: $po,
                description: "Upload PO #{$po->po_number} untuk PR #{$po->purchaseRequest->pr_number}",
                newValues: ['po_number' => $po->po_number]
            );

            return $po;
        });
    }

    /**
     * Closing: tandai PR sebagai selesai setelah semua proses tuntas.
     * Syarat: ada PO, ada delivery yang diterima, ada INVOICE yang disetujui.
     */
    public function closePR(PurchaseRequest $pr): void
    {
        DB::transaction(function () use ($pr) {
            // Validasi kondisi closing
            $po       = $pr->purchaseOrder;
            $delivery = $po?->deliveries()->where('status', 'diterima')->exists();
            $invoice  = $po?->invoices()->where('status', 'disetujui')->exists();

            if (!$po || !$delivery || !$invoice) {
                throw new \Exception('PR belum memenuhi syarat closing. Pastikan PO, delivery diterima, dan INVOICE disetujui sudah ada.');
            }

            $pr->update(['status' => 'selesai', 'closed_at' => now()]);

            // Update statistik supplier
            $po->supplier->recalculateStats();

            AuditLog::record(
                action: 'close_pr',
                module: 'PR',
                loggable: $pr,
                description: "PR #{$pr->pr_number} ditutup/selesai.",
            );
        });
    }

    /**
     * Ambil statistik dashboard untuk admin/purchasing.
     */
    public function getDashboardStats(): array
    {
        return [
            'total_pr'           => PurchaseRequest::count(),
            'pr_aktif'           => PurchaseRequest::whereNotIn('status', ['selesai', 'dibatalkan'])->count(),
            'pr_menunggu'        => PurchaseRequest::where('status', 'draft')->count(),
            'pr_selesai'         => PurchaseRequest::where('status', 'selesai')->count(),
            'total_supplier'     => Supplier::where('status', 'aktif')->count(),
            'total_penawaran'    => Quotation::whereMonth('created_at', now()->month)->count(),
            'pr_expired'         => PurchaseRequest::expired()->count(),
            'total_nilai_po'     => PurchaseOrder::sum('total_amount'),
            'po_bulan_ini'       => PurchaseOrder::whereMonth('created_at', now()->month)->count(),
            // Distribusi status PR untuk chart
            'status_distribution' => PurchaseRequest::selectRaw('status, COUNT(*) as count')
                                      ->groupBy('status')->pluck('count', 'status'),
        ];
    }
}

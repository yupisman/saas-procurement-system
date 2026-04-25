<?php
// =============================================================================
// FILE: app/Models/PurchaseRequest.php
// PURPOSE: Model PR - dokumen READ-ONLY dari ERP berupa PDF.
//          Sistem TIDAK membuat PR, hanya mendistribusikan dan tracking.
// =============================================================================
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class PurchaseRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pr_number', 'title', 'category_id', 'file_path', 'file_name',
        'file_size', 'status', 'deadline', 'notes', 'created_by',
        'assigned_to', 'distributed_at', 'closed_at',
    ];

    protected $casts = [
        'deadline'        => 'date',
        'distributed_at'  => 'datetime',
        'closed_at'       => 'datetime',
        'file_size'       => 'integer',
    ];

    // ── Status Flow ───────────────────────────────────────────────────────────
    // draft → didistribusi → penawaran → evaluasi → disetujui → po_diterbitkan
    //       → pengiriman → selesai
    //       OR: dibatalkan (kapan saja)

    public function isEditable(): bool
    {
        // PR hanya bisa diubah statusnya saat masih draft
        return $this->status === 'draft';
    }

    public function isExpired(): bool
    {
        return $this->deadline->isPast() &&
               !in_array($this->status, ['selesai', 'dibatalkan']);
    }

    // URL download file PDF PR (melalui route protected, bukan langsung)
    public function getFileUrlAttribute(): string
    {
        return route('pr.download', $this->id);
    }

    // Ukuran file dalam format human-readable
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' Bytes';
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeByStatus($q, string $status) { return $q->where('status', $status); }
    public function scopeActive($q) {
        return $q->whereNotIn('status', ['selesai', 'dibatalkan']);
    }
    public function scopeExpired($q) {
        return $q->where('deadline', '<', now())
                 ->whereNotIn('status', ['selesai', 'dibatalkan']);
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function category()         { return $this->belongsTo(Category::class); }
    public function createdBy()        { return $this->belongsTo(User::class, 'created_by'); }
    public function assignedTo()       { return $this->belongsTo(User::class, 'assigned_to'); }
    public function quotations()       { return $this->hasMany(Quotation::class); }
    public function prSuppliers()      { return $this->hasMany(PrSupplier::class); }
    public function suppliers()        { return $this->belongsToMany(Supplier::class, 'pr_suppliers'); }
    public function purchaseOrder()    { return $this->hasOne(PurchaseOrder::class); }
    public function approvals()        { return $this->morphMany(Approval::class, 'approvable'); }
    public function auditLogs()        { return $this->morphMany(AuditLog::class, 'loggable'); }

    // Best quotation (harga terbaik yang sudah dipilih)
    public function bestQuotation()
    {
        return $this->hasOne(Quotation::class)->where('is_best', true);
    }

    // Semua quotation yang masuk (urut dari terbaik)
    public function rankedQuotations()
    {
        return $this->hasMany(Quotation::class)->orderByDesc('score');
    }
}

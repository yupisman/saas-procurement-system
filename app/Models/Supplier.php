<?php
// =============================================================================
// FILE: app/Models/Supplier.php
// PURPOSE: Model supplier dengan scoring, kategori, dan relasi ke semua dokumen
// =============================================================================
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'company_name', 'npwp', 'pic_name', 'pic_phone',
        'address', 'city', 'province', 'status', 'blacklist_reason',
        'rating', 'total_po', 'total_quotation', 'win_rate', 'notes',
    ];

    protected $casts = [
        'rating'           => 'decimal:2',
        'win_rate'         => 'decimal:2',
        'total_po'         => 'integer',
        'total_quotation'  => 'integer',
    ];

    // ── Status Scopes ─────────────────────────────────────────────────────────
    public function scopeAktif($q)      { return $q->where('status', 'aktif'); }
    public function scopeBlacklist($q)  { return $q->where('status', 'blacklist'); }

    // ── Ranking: urutkan berdasarkan skor composite ───────────────────────────
    // Skor = (rating * 40%) + (win_rate * 30%) + (total_po * 30%)
    public function scopeByRanking($q)
    {
        return $q->orderByRaw(
            '((rating / 5 * 40) + (win_rate / 100 * 30) + (LEAST(total_po, 50) / 50 * 30)) DESC'
        );
    }

    /**
     * Hitung ulang win_rate berdasarkan data aktual.
     * Dipanggil setelah setiap penawaran diputuskan.
     */
    public function recalculateStats(): void
    {
        $total     = $this->quotations()->count();
        $won       = $this->quotations()->where('status', 'selected')->count();
        $winRate   = $total > 0 ? ($won / $total) * 100 : 0;
        $totalPO   = $this->purchaseOrders()->count();

        $this->update([
            'total_quotation' => $total,
            'total_po'        => $totalPO,
            'win_rate'        => $winRate,
        ]);
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function user()            { return $this->belongsTo(User::class); }
    public function categories()      { return $this->belongsToMany(Category::class, 'supplier_categories'); }
    public function quotations()      { return $this->hasMany(Quotation::class); }
    public function purchaseOrders()  { return $this->hasMany(PurchaseOrder::class); }
    public function deliveries()      { return $this->hasMany(Delivery::class); }
    public function invoices()        { return $this->hasMany(Invoice::class); }
    public function prSuppliers()     { return $this->hasMany(PrSupplier::class); }
}

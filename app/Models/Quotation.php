<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_request_id', 'supplier_id', 'quotation_number', 'total_amount',
        'delivery_days', 'valid_until', 'terms', 'notes', 'status', 'score',
        'is_best', 'rejection_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'valid_until'    => 'date',
        'reviewed_at'    => 'datetime',
        'total_amount'   => 'decimal:2',
        'score'          => 'decimal:2',
        'is_best'        => 'boolean',
    ];

    public function calculateScore(float $lowestAmount, int $fastestDays): float
    {
        $priceScore    = $lowestAmount > 0 ? ($lowestAmount / $this->total_amount) * 100 : 0;
        $deliveryScore = $fastestDays > 0 ? ($fastestDays / $this->delivery_days) * 100 : 0;
        $daysValid     = now()->diffInDays($this->valid_until);
        $validScore    = min(($daysValid / 30) * 100, 100);
        $score = ($priceScore * 0.5) + ($deliveryScore * 0.3) + ($validScore * 0.2);
        $this->update(['score' => $score]);
        return $score;
    }

    public function markAsBest(): void
    {
        static::where('purchase_request_id', $this->purchase_request_id)
              ->update(['is_best' => false]);
        $this->update(['is_best' => true, 'status' => 'selected']);
    }

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function supplier()        { return $this->belongsTo(Supplier::class); }
    public function files()           { return $this->hasMany(QuotationFile::class); }
    public function items()           { return $this->hasMany(QuotationItem::class); }
    public function reviewedBy()      { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function purchaseOrder()   { return $this->hasOne(PurchaseOrder::class); }
}

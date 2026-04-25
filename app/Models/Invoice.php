<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_order_id', 'supplier_id', 'delivery_id', 'invoice_number',
        'invoice_date', 'due_date', 'amount', 'tax_amount', 'total_amount',
        'file_path', 'file_name', 'file_size', 'status',
        'verified_at', 'verified_by', 'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date'     => 'date',
        'verified_at'  => 'datetime',
        'amount'       => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast()
               && !in_array($this->status, ['dibayar']);
    }

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function supplier()      { return $this->belongsTo(Supplier::class); }
    public function delivery()      { return $this->belongsTo(Delivery::class); }
    public function verifiedBy()    { return $this->belongsTo(User::class, 'verified_by'); }
    public function fakturPajak()   { return $this->hasOne(FakturPajak::class); }
}

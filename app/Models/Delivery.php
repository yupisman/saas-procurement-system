<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_order_id', 'supplier_id', 'delivery_number', 'delivery_date',
        'received_date', 'carrier', 'tracking_number', 'status', 'notes', 'received_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'received_date' => 'date',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function supplier()      { return $this->belongsTo(Supplier::class); }
    public function photos()        { return $this->hasMany(DeliveryPhoto::class); }
    public function receivedBy()    { return $this->belongsTo(User::class, 'received_by'); }
    public function invoices()      { return $this->hasMany(Invoice::class); }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_request_id', 'quotation_id', 'supplier_id', 'po_number',
        'file_path', 'file_name', 'file_size', 'total_amount', 'delivery_deadline',
        'status', 'sent_to_supplier_at', 'confirmed_by_supplier_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'delivery_deadline'          => 'date',
        'sent_to_supplier_at'        => 'datetime',
        'confirmed_by_supplier_at'   => 'datetime',
        'total_amount'               => 'decimal:2',
    ];

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function quotation()       { return $this->belongsTo(Quotation::class); }
    public function supplier()        { return $this->belongsTo(Supplier::class); }
    public function createdBy()       { return $this->belongsTo(User::class, 'created_by'); }
    public function deliveries()      { return $this->hasMany(Delivery::class); }
    public function invoices()        { return $this->hasMany(Invoice::class); }
}

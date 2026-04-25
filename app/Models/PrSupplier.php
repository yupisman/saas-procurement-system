<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrSupplier extends Model
{
    protected $table = 'pr_suppliers';

    protected $fillable = [
        'purchase_request_id', 'supplier_id', 'status', 'sent_at', 'opened_at',
    ];

    protected $casts = [
        'sent_at'   => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function supplier()        { return $this->belongsTo(Supplier::class); }
}

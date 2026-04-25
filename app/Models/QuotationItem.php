<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id', 'item_name', 'unit', 'quantity',
        'unit_price', 'total_price', 'specifications',
    ];

    protected $casts = [
        'quantity'    => 'decimal:2',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });
    }

    public function quotation() { return $this->belongsTo(Quotation::class); }
}

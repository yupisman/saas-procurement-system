<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FakturPajak extends Model
{
    use SoftDeletes;

    protected $table = 'faktur_pajak';

    protected $fillable = [
        'invoice_id', 'supplier_id', 'nomor_faktur', 'tanggal_faktur',
        'dpp', 'ppn', 'file_path', 'file_name', 'file_size', 'status', 'notes',
    ];

    protected $casts = [
        'tanggal_faktur' => 'date',
        'dpp'            => 'decimal:2',
        'ppn'            => 'decimal:2',
    ];

    public function invoice()  { return $this->belongsTo(Invoice::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
}

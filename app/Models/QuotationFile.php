<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationFile extends Model
{
    protected $fillable = [
        'quotation_id', 'file_path', 'file_name', 'file_type', 'file_size', 'category',
    ];

    public function quotation() { return $this->belongsTo(Quotation::class); }
}

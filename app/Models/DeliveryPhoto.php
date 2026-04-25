<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryPhoto extends Model
{
    protected $fillable = [
        'delivery_id', 'file_path', 'file_name', 'type', 'caption', 'uploaded_by',
    ];

    public function delivery()    { return $this->belongsTo(Delivery::class); }
    public function uploadedBy()  { return $this->belongsTo(User::class, 'uploaded_by'); }
}

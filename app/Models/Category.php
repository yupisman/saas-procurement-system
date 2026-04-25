<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'description', 'is_active'];

    public function suppliers()        { return $this->belongsToMany(Supplier::class, 'supplier_categories'); }
    public function purchaseRequests() { return $this->hasMany(PurchaseRequest::class); }
}

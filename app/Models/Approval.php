<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $fillable = [
        'approver_id', 'level', 'status', 'comments', 'decided_at',
        'approvable_type', 'approvable_id',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function approver()    { return $this->belongsTo(User::class, 'approver_id'); }
    public function approvable()  { return $this->morphTo(); }
}

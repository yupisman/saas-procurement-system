<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementNotification extends Model
{
    protected $table = 'procurement_notifications';

    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'data',
        'notifiable_type', 'notifiable_id', 'is_read', 'read_at',
    ];

    protected $casts = [
        'data'     => 'array',
        'is_read'  => 'boolean',
        'read_at'  => 'datetime',
    ];

    public function user()        { return $this->belongsTo(User::class); }
    public function notifiable()  { return $this->morphTo(); }

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update(['is_read' => true, 'read_at' => now()]);
        }
    }
}

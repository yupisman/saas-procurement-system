<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'module', 'loggable_type', 'loggable_id',
        'description', 'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function loggable() { return $this->morphTo(); }

    public static function record(
        string $action,
        string $module,
        Model  $loggable,
        string $description,
        array  $oldValues = [],
        array  $newValues = []
    ): self {
        return static::create([
            'user_id'       => auth()->id(),
            'action'        => $action,
            'module'        => $module,
            'loggable_type' => get_class($loggable),
            'loggable_id'   => $loggable->id,
            'description'   => $description,
            'old_values'    => $oldValues ?: null,
            'new_values'    => $newValues ?: null,
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
        ]);
    }
}

<?php
// =============================================================================
// FILE: app/Models/User.php
// PURPOSE: Model user dengan role-based access untuk admin, purchasing, supplier
// =============================================================================
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ── Role Helpers ──────────────────────────────────────────────────────────
    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isPurchasing(): bool { return $this->role === 'purchasing'; }
    public function isSupplier(): bool   { return $this->role === 'supplier'; }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function supplier() { return $this->hasOne(Supplier::class); }
    public function purchaseRequests() { return $this->hasMany(PurchaseRequest::class, 'created_by'); }
    public function auditLogs() { return $this->hasMany(AuditLog::class); }
    public function notifications() { return $this->hasMany(ProcurementNotification::class); }
    public function approvals() { return $this->hasMany(Approval::class, 'approver_id'); }
}

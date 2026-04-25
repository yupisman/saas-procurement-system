<?php
// =============================================================================
// FILE: app/Providers/AppServiceProvider.php
// PURPOSE: Registrasi service provider, bindings, dan boot logic aplikasi
// =============================================================================
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;
use App\Services\ProcurementService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register: bind services ke container
     */
    public function register(): void
    {
        // ProcurementService sebagai singleton (hemat memory)
        $this->app->singleton(ProcurementService::class);
    }

    /**
     * Boot: konfigurasi yang berjalan setelah semua service terdaftar
     */
    public function boot(): void
    {
        // ── Strict mode di development (catch lazy loading, mass assignment) ─
        if ($this->app->environment('local')) {
            Model::shouldBeStrict();
        }

        // ── Filament: hanya admin dan purchasing yang bisa akses panel ────────
        Gate::define('viewFilamentDashboard', function ($user) {
            return in_array($user->role, ['admin', 'purchasing']) && $user->is_active;
        });

        // ── Policy untuk Filament resources ───────────────────────────────────
        // Admin bisa semua, purchasing dibatasi sesuai modul
        Gate::before(function ($user, $ability) {
            if ($user->role === 'admin' && $user->is_active) {
                return true; // Admin bypass semua gate
            }
        });
    }
}

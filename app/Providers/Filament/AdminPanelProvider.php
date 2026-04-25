<?php
// =============================================================================
// FILE: app/Providers/Filament/AdminPanelProvider.php
// PURPOSE: Konfigurasi Filament panel admin untuk tim purchasing dan admin.
// =============================================================================
namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\DeliveryResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ProcurementStatsWidget;
use App\Filament\Widgets\PRStatusChartWidget;
use App\Filament\Widgets\SupplierRankingWidget;
use App\Filament\Widgets\RecentActivityWidget;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()

            // ── Branding ─────────────────────────────────────────────────────
            ->brandName('Sistem Pengadaan')
            ->favicon(asset('images/favicon.png'))

            // ── Warna sesuai spesifikasi: Emerald primary ─────────────────────
            ->colors([
                'primary'   => Color::Emerald,
                'danger'    => Color::Red,
                'warning'   => Color::Amber,
                'success'   => Color::Green,
                'info'      => Color::Blue,
            ])

            // ── Dark mode support ─────────────────────────────────────────────
            ->darkMode(true)

            // ── Resources (modul-modul sistem) ────────────────────────────────
            ->resources([
                PurchaseRequestResource::class,
                SupplierResource::class,
                QuotationResource::class,
                PurchaseOrderResource::class,
                DeliveryResource::class,
                InvoiceResource::class,
                AuditLogResource::class,
            ])

            // ── Pages ─────────────────────────────────────────────────────────
            ->pages([
                Dashboard::class,
            ])

            // ── Widgets dashboard ─────────────────────────────────────────────
            ->widgets([
                ProcurementStatsWidget::class,
                PRStatusChartWidget::class,
                SupplierRankingWidget::class,
                RecentActivityWidget::class,
            ])

            // ── Navigation groups ─────────────────────────────────────────────
            ->navigationGroups([
                'Pengadaan',
                'Dokumen',
                'Master Data',
                'Sistem',
            ])

            // ── Middleware ────────────────────────────────────────────────────
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])

            // ── Auth guard: hanya admin dan purchasing ────────────────────────
            ->authGuard('web');
    }
}

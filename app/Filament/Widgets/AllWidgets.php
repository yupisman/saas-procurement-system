<?php
// =============================================================================
// FILE: app/Filament/Widgets/ProcurementStatsWidget.php
// PURPOSE: Widget statistik ringkasan dashboard utama procurement
// =============================================================================
namespace App\Filament\Widgets;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Quotation;
use App\Models\PurchaseOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProcurementStatsWidget extends BaseWidget
{
    // Refresh setiap 30 detik (real-time feel)
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $prAktif      = PurchaseRequest::whereNotIn('status', ['selesai', 'dibatalkan'])->count();
        $prExpired    = PurchaseRequest::expired()->count();
        $supplierAktif = Supplier::aktif()->count();
        $penawaran    = Quotation::where('status', 'submitted')->count();
        $nilaiPO      = PurchaseOrder::whereMonth('created_at', now()->month)->sum('total_amount');

        return [
            Stat::make('PR Aktif', $prAktif)
                ->description($prExpired > 0 ? "{$prExpired} melewati deadline" : 'Semua dalam batas waktu')
                ->descriptionIcon($prExpired > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($prExpired > 0 ? 'danger' : 'success')
                ->chart(
                    PurchaseRequest::selectRaw('COUNT(*) as count')
                        ->whereDate('created_at', '>=', now()->subDays(7))
                        ->groupBy(\DB::raw('DATE(created_at)'))
                        ->pluck('count')
                        ->toArray()
                ),

            Stat::make('Penawaran Masuk', $penawaran)
                ->description('Menunggu review purchasing')
                ->descriptionIcon('heroicon-m-inbox')
                ->color($penawaran > 0 ? 'warning' : 'gray'),

            Stat::make('Supplier Aktif', $supplierAktif)
                ->description('Terdaftar dan aktif')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),

            Stat::make('Nilai PO Bulan Ini', 'Rp ' . number_format($nilaiPO / 1000000, 0) . 'Jt')
                ->description('Purchase Order diterbitkan bulan ini')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}


// =============================================================================
// FILE: app/Filament/Widgets/PRStatusChartWidget.php
// PURPOSE: Pie/bar chart distribusi status PR
// =============================================================================
namespace App\Filament\Widgets;

use App\Models\PurchaseRequest;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PRStatusChartWidget extends ChartWidget
{
    protected static ?string $heading  = 'Distribusi Status PR';
    protected static ?int    $sort     = 2;
    protected static string  $color    = 'info';
    protected static ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $statuses = PurchaseRequest::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $labels = [];
        $data   = [];
        $colors = [];

        $statusMap = [
            'draft'          => ['label' => 'Draft',             'color' => '#6b7280'],
            'didistribusi'   => ['label' => 'Didistribusi',      'color' => '#f59e0b'],
            'penawaran'      => ['label' => 'Penawaran Masuk',   'color' => '#3b82f6'],
            'evaluasi'       => ['label' => 'Evaluasi',          'color' => '#8b5cf6'],
            'disetujui'      => ['label' => 'Disetujui',         'color' => '#10b981'],
            'po_diterbitkan' => ['label' => 'PO Diterbitkan',    'color' => '#059669'],
            'pengiriman'     => ['label' => 'Dalam Pengiriman',  'color' => '#0891b2'],
            'selesai'        => ['label' => 'Selesai',           'color' => '#16a34a'],
            'dibatalkan'     => ['label' => 'Dibatalkan',        'color' => '#dc2626'],
        ];

        foreach ($statuses as $status => $count) {
            $map      = $statusMap[$status] ?? ['label' => $status, 'color' => '#9ca3af'];
            $labels[] = $map['label'];
            $data[]   = $count;
            $colors[] = $map['color'];
        }

        return [
            'datasets' => [[
                'label'           => 'Jumlah PR',
                'data'            => $data,
                'backgroundColor' => $colors,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}


// =============================================================================
// FILE: app/Filament/Widgets/SupplierRankingWidget.php
// PURPOSE: Tabel ranking supplier berdasarkan scoring composite
// =============================================================================
namespace App\Filament\Widgets;

use App\Models\Supplier;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SupplierRankingWidget extends BaseWidget
{
    protected static ?string $heading = '🏆 Ranking Supplier';
    protected static ?int    $sort    = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Supplier::aktif()
                    ->byRanking()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex()
                    ->width('50px'),

                Tables\Columns\TextColumn::make('company_name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => str_repeat('★', round($state)) . ' ' . number_format($state, 1))
                    ->color('warning'),

                Tables\Columns\TextColumn::make('win_rate')
                    ->label('Win Rate')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . '%')
                    ->color(fn ($state) => $state >= 50 ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('total_po')
                    ->label('Total PO')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_quotation')
                    ->label('Total Penawaran')
                    ->badge()
                    ->color('gray'),
            ])
            ->paginated(false);
    }
}


// =============================================================================
// FILE: app/Filament/Widgets/RecentActivityWidget.php
// PURPOSE: Log aktivitas terbaru sistem (real-time dengan polling)
// =============================================================================
namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    protected static ?string $heading        = '📋 Aktivitas Terbaru';
    protected static ?int    $sort           = 4;
    protected static ?string $pollingInterval = '15s'; // Refresh 15 detik
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(AuditLog::with('user')->latest()->limit(15))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->default('System'),

                Tables\Columns\BadgeColumn::make('module')
                    ->label('Modul')
                    ->colors([
                        'info'    => 'PR',
                        'warning' => 'Quotation',
                        'success' => 'PO',
                        'danger'  => 'Invoice',
                    ]),

                Tables\Columns\TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated(false);
    }
}

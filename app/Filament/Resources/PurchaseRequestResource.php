<?php
// =============================================================================
// FILE: app/Filament/Resources/PurchaseRequestResource.php
// PURPOSE: Resource Filament untuk manajemen PR di panel admin/purchasing.
//          Mencakup: upload PDF, distribusi ke supplier, monitoring status.
// =============================================================================
namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseRequestResource\Pages;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Category;
use App\Services\ProcurementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class PurchaseRequestResource extends Resource
{
    protected static ?string $model = PurchaseRequest::class;

    // ── Icon navigasi (Heroicons) ──────────────────────────────────────────────
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Pengadaan';
    protected static ?string $navigationLabel = 'Daftar PR';
    protected static ?string $modelLabel      = 'Purchase Request';
    protected static ?int    $navigationSort  = 1;

    // Badge jumlah PR aktif di navigasi
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereNotIn('status', ['selesai', 'dibatalkan'])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    // ── Form (Upload PR) ───────────────────────────────────────────────────────
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi PR')
                    ->description('Data PR dari ERP. PR berupa file PDF yang diupload, sistem tidak membuat PR baru.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\TextInput::make('pr_number')
                            ->label('Nomor PR')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('PR-2024-001')
                            ->helperText('Nomor PR sesuai yang tertera di dokumen ERP'),

                        Forms\Components\TextInput::make('title')
                            ->label('Judul / Deskripsi PR')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull()
                            ->placeholder('Pengadaan Material Conveyor Belt...'),

                        Forms\Components\Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('code')->required(),
                            ]),

                        Forms\Components\DatePicker::make('deadline')
                            ->label('Deadline Penawaran')
                            ->required()
                            ->minDate(now()->addDay())
                            ->displayFormat('d/m/Y')
                            ->helperText('Batas waktu supplier mengirimkan penawaran'),

                        Forms\Components\Select::make('assigned_to')
                            ->label('Assigned ke')
                            ->relationship('assignedTo', 'name', function ($query) {
                                $query->whereIn('role', ['admin', 'purchasing']);
                            })
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Internal')
                            ->rows(3)
                            ->columnSpanFull()
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Upload Dokumen PR (PDF dari ERP)')
                    ->description(new HtmlString('<span class="text-amber-500 font-medium">⚠️ Upload PDF PR dari ERP. Dokumen ini bersifat READ ONLY, tidak dapat diedit di sistem.</span>'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('File PDF PR')
                            ->required()
                            ->disk('public')
                            ->directory('pr')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240) // 10MB
                            ->downloadable()
                            ->openable()
                            ->helperText('Hanya file PDF. Maksimal 10MB.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    // ── Table (Daftar PR) ──────────────────────────────────────────────────────
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pr_number')
                    ->label('No. PR')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Judul PR')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->title),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('info'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'draft',
                        'warning' => 'didistribusi',
                        'info'    => 'penawaran',
                        'primary' => 'evaluasi',
                        'success' => fn ($state) => in_array($state, ['disetujui', 'selesai']),
                        'danger'  => fn ($state) => in_array($state, ['dibatalkan']),
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'draft'          => 'Draft',
                        'didistribusi'   => 'Didistribusi',
                        'penawaran'      => 'Penawaran Masuk',
                        'evaluasi'       => 'Evaluasi',
                        'disetujui'      => 'Disetujui',
                        'po_diterbitkan' => 'PO Diterbitkan',
                        'pengiriman'     => 'Dalam Pengiriman',
                        'selesai'        => 'Selesai',
                        'dibatalkan'     => 'Dibatalkan',
                        default          => $state,
                    }),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Deadline')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->deadline->isPast() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('quotations_count')
                    ->label('Penawaran')
                    ->counts('quotations')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('PIC')
                    ->default('-'),

                Tables\Columns\TextColumn::make('distributed_at')
                    ->label('Tgl Distribusi')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])

            // ── Filter ─────────────────────────────────────────────────────────
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft'          => 'Draft',
                        'didistribusi'   => 'Didistribusi',
                        'penawaran'      => 'Penawaran Masuk',
                        'evaluasi'       => 'Evaluasi',
                        'disetujui'      => 'Disetujui',
                        'po_diterbitkan' => 'PO Diterbitkan',
                        'pengiriman'     => 'Dalam Pengiriman',
                        'selesai'        => 'Selesai',
                        'dibatalkan'     => 'Dibatalkan',
                    ]),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),

                Tables\Filters\Filter::make('expired')
                    ->label('Deadline Terlewat')
                    ->query(fn (Builder $q) => $q->where('deadline', '<', now())
                                                  ->whereNotIn('status', ['selesai', 'dibatalkan'])),

                Tables\Filters\Filter::make('this_month')
                    ->label('Bulan Ini')
                    ->query(fn (Builder $q) => $q->whereMonth('created_at', now()->month)),
            ])

            // ── Aksi pada baris tabel ──────────────────────────────────────────
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat'),
                Tables\Actions\EditAction::make()->label('Edit')->visible(fn ($record) => $record->status === 'draft'),

                // Tombol distribusi PR ke supplier
                Tables\Actions\Action::make('distribusi')
                    ->label('Distribusi')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->form([
                        Forms\Components\Select::make('supplier_ids')
                            ->label('Pilih Supplier')
                            ->multiple()
                            ->required()
                            ->options(
                                Supplier::aktif()->pluck('company_name', 'id')
                            )
                            ->searchable()
                            ->helperText('Pilih supplier yang akan menerima undangan penawaran ini'),
                    ])
                    ->action(function ($record, array $data) {
                        app(ProcurementService::class)->distributePR($record, $data['supplier_ids']);
                        Notification::make()
                            ->title("PR #{$record->pr_number} berhasil didistribusikan ke " . count($data['supplier_ids']) . " supplier.")
                            ->success()->send();
                    }),

                // Tombol evaluasi penawaran
                Tables\Actions\Action::make('evaluasi')
                    ->label('Evaluasi Penawaran')
                    ->icon('heroicon-o-scale')
                    ->color('primary')
                    ->visible(fn ($record) => $record->status === 'penawaran')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $best = app(ProcurementService::class)->evaluateQuotations($record);
                        Notification::make()
                            ->title($best ? "Evaluasi selesai. Penawaran terbaik: {$best->supplier->company_name}" : 'Tidak ada penawaran.')
                            ->success()->send();
                    }),

                // Closing PR
                Tables\Actions\Action::make('closing')
                    ->label('Closing')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pengiriman')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Closing PR')
                    ->modalDescription('Pastikan delivery sudah diterima dan INVOICE sudah disetujui.')
                    ->action(function ($record) {
                        try {
                            app(ProcurementService::class)->closePR($record);
                            Notification::make()->title("PR #{$record->pr_number} berhasil ditutup.")->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])

            ->defaultSort('created_at', 'desc')
            ->striped()
            ->poll('30s'); // Refresh otomatis setiap 30 detik
    }

    public static function getRelations(): array
    {
        return [
            // Bisa tambah QuotationRelationManager, dll.
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPurchaseRequests::route('/'),
            'create' => Pages\CreatePurchaseRequest::route('/create'),
            'view'   => Pages\ViewPurchaseRequest::route('/{record}'),
            'edit'   => Pages\EditPurchaseRequest::route('/{record}/edit'),
        ];
    }

    // Soft delete support
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

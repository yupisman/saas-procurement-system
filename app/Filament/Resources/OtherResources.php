<?php
// =============================================================================
// FILE: app/Filament/Resources/AuditLogResource.php
// PURPOSE: Read-only audit trail viewer di admin panel
// =============================================================================
namespace App\Filament\Resources;

use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    protected static ?string $model           = AuditLog::class;
    protected static ?string $navigationIcon  = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Sistem';
    protected static ?string $navigationLabel = 'Audit Log';
    protected static ?int    $navigationSort  = 99;

    // Audit log TIDAK bisa dibuat, diedit, atau dihapus dari UI
    public static function canCreate(): bool  { return false; }
    public static function canEdit($record):bool { return false; }
    public static function canDelete($record):bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]); // Empty — audit log read-only
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->default('System'),

                Tables\Columns\BadgeColumn::make('module')
                    ->label('Modul')
                    ->colors([
                        'info'    => 'PR',
                        'warning' => 'Quotation',
                        'success' => 'PO',
                        'danger'  => fn ($state) => in_array($state, ['Invoice', 'FakturPajak']),
                        'gray'    => 'Auth',
                    ]),

                Tables\Columns\TextColumn::make('action')
                    ->label('Aksi')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->searchable()
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('Browser')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(40),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('module')
                    ->options([
                        'PR'        => 'PR',
                        'Quotation' => 'Quotation',
                        'PO'        => 'PO',
                        'Invoice'   => 'Invoice',
                        'Auth'      => 'Auth',
                        'Delivery'  => 'Delivery',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn (Builder $q) => $q->whereDate('created_at', today())),

                Tables\Filters\Filter::make('this_week')
                    ->label('Minggu Ini')
                    ->query(fn (Builder $q) => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->poll('15s'); // Real-time refresh
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs::route('/'),
        ];
    }
}


// =============================================================================
// FILE: app/Filament/Resources/PurchaseOrderResource.php
// PURPOSE: Upload dan monitoring PO (PDF dari ERP) di panel admin
// =============================================================================
namespace App\Filament\Resources;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Services\ProcurementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model           = PurchaseOrder::class;
    protected static ?string $navigationIcon  = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Dokumen';
    protected static ?string $navigationLabel = 'Purchase Order';
    protected static ?int    $navigationSort  = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Kaitkan PO ke PR & Penawaran')
                ->description(new HtmlString('<span class="text-amber-600 font-medium">⚠️ PO hanya upload PDF dari ERP. Sistem tidak generate PO.</span>'))
                ->schema([
                    Forms\Components\Select::make('purchase_request_id')
                        ->label('PR')
                        ->options(
                            PurchaseRequest::where('status', 'disetujui')
                                ->get()->mapWithKeys(fn ($pr) => [$pr->id => "PR #{$pr->pr_number} — {$pr->title}"])
                        )
                        ->required()
                        ->searchable()
                        ->live() // Reactive: update pilihan quotation saat PR dipilih
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('quotation_id', null)),

                    Forms\Components\Select::make('quotation_id')
                        ->label('Penawaran Terpilih')
                        ->options(function (Forms\Get $get) {
                            $prId = $get('purchase_request_id');
                            if (!$prId) return [];
                            return Quotation::where('purchase_request_id', $prId)
                                ->where('is_best', true)
                                ->with('supplier')
                                ->get()
                                ->mapWithKeys(fn ($q) => [
                                    $q->id => "{$q->supplier->company_name} — Rp " . number_format($q->total_amount, 0, ',', '.')
                                ]);
                        })
                        ->required()
                        ->searchable(),

                    Forms\Components\TextInput::make('po_number')
                        ->label('Nomor PO')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->placeholder('PO-2024-001'),

                    Forms\Components\TextInput::make('total_amount')
                        ->label('Total Nilai PO (Rp)')
                        ->required()
                        ->numeric()
                        ->prefix('Rp'),

                    Forms\Components\DatePicker::make('delivery_deadline')
                        ->label('Deadline Pengiriman')
                        ->required()
                        ->displayFormat('d/m/Y'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Upload PDF PO dari ERP')
                ->schema([
                    Forms\Components\FileUpload::make('file_path')
                        ->label('File PDF PO')
                        ->required()
                        ->disk('public')
                        ->directory('po')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240)
                        ->downloadable()
                        ->openable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('No. PO')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('purchaseRequest.pr_number')
                    ->label('PR')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier.company_name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Nilai PO')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_deadline')
                    ->label('Deadline Kirim')
                    ->date('d/m/Y')
                    ->color(fn ($record) => $record->delivery_deadline->isPast() ? 'danger' : 'success'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'info'    => 'diterbitkan',
                        'warning' => fn ($s) => in_array($s, ['dikirim', 'dikonfirmasi', 'dalam_proses']),
                        'primary' => 'dikirim_barang',
                        'success' => fn ($s) => in_array($s, ['diterima', 'selesai']),
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tgl Upload')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat'),
                Tables\Actions\EditAction::make()->label('Edit'),

                // Kirim PO ke supplier (ubah status)
                Tables\Actions\Action::make('send_to_supplier')
                    ->label('Kirim ke Supplier')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn ($r) => $r->status === 'diterbitkan')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status'                  => 'dikirim',
                            'sent_to_supplier_at'     => now(),
                        ]);
                        Notification::make()->title("PO #{$record->po_number} dikirim ke supplier.")->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // Hook setelah create: panggil service uploadPO
    public static function getPages(): array
    {
        return [
            'index'  => \App\Filament\Resources\PurchaseOrderResource\Pages\ListPurchaseOrders::route('/'),
            'create' => \App\Filament\Resources\PurchaseOrderResource\Pages\CreatePurchaseOrder::route('/create'),
            'view'   => \App\Filament\Resources\PurchaseOrderResource\Pages\ViewPurchaseOrder::route('/{record}'),
            'edit'   => \App\Filament\Resources\PurchaseOrderResource\Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}


// =============================================================================
// FILE: app/Filament/Resources/InvoiceResource.php
// PURPOSE: Monitoring dan verifikasi INVOICE + FAKTUR PAJAK di admin panel
// =============================================================================
namespace App\Filament\Resources;

use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class InvoiceResource extends Resource
{
    protected static ?string $model           = Invoice::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-currency-dollar';
    protected static ?string $navigationGroup = 'Dokumen';
    protected static ?string $navigationLabel = 'INVOICE';
    protected static ?int    $navigationSort  = 4;

    public static function canCreate(): bool { return false; } // INVOICE hanya dari supplier

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Detail INVOICE')
                ->schema([
                    Forms\Components\TextInput::make('invoice_number')->label('No. INVOICE')->disabled(),
                    Forms\Components\TextInput::make('total_amount')->label('Total')->disabled()
                        ->formatStateUsing(fn ($s) => 'Rp ' . number_format($s, 0, ',', '.')),
                    Forms\Components\DatePicker::make('invoice_date')->label('Tgl INVOICE')->disabled(),
                    Forms\Components\DatePicker::make('due_date')->label('Jatuh Tempo')->disabled(),
                    Forms\Components\TextInput::make('supplier.company_name')->label('Supplier')->disabled(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'diterima'     => 'Diterima',
                            'diverifikasi' => 'Diverifikasi',
                            'disetujui'    => 'Disetujui',
                            'dibayar'      => 'Dibayar',
                            'ditolak'      => 'Ditolak',
                        ]),
                    Forms\Components\Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->label('No. INVOICE')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('supplier.company_name')->label('Supplier')->searchable(),
                Tables\Columns\TextColumn::make('purchaseOrder.po_number')->label('No. PO'),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money('IDR'),
                Tables\Columns\TextColumn::make('due_date')->label('Jatuh Tempo')->date('d/m/Y')
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : 'success'),
                Tables\Columns\BadgeColumn::make('status')->colors([
                    'info' => 'diterima', 'warning' => 'diverifikasi',
                    'success' => fn ($s) => in_array($s, ['disetujui', 'dibayar']),
                    'danger' => 'ditolak',
                ]),
                Tables\Columns\IconColumn::make('fakturPajak')->label('FAKTUR PAJAK')
                    ->boolean()->trueIcon('heroicon-o-check-circle')->falseIcon('heroicon-o-x-circle')
                    ->state(fn ($record) => (bool) $record->fakturPajak),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Verifikasi'),
                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($r) => in_array($r->status, ['diterima', 'diverifikasi']))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'disetujui', 'verified_at' => now(), 'verified_by' => auth()->id()]);
                        Notification::make()->title("INVOICE #{$record->invoice_number} disetujui.")->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\InvoiceResource\Pages\ListInvoices::route('/'),
            'view'  => \App\Filament\Resources\InvoiceResource\Pages\ViewInvoice::route('/{record}'),
            'edit'  => \App\Filament\Resources\InvoiceResource\Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}

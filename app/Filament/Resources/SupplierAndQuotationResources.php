<?php
// =============================================================================
// FILE: app/Filament/Resources/SupplierResource.php
// PURPOSE: Manajemen data supplier: CRUD, blacklist, ranking, kategori
// =============================================================================
namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;

class SupplierResource extends Resource
{
    protected static ?string $model           = Supplier::class;
    protected static ?string $navigationIcon  = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Supplier';
    protected static ?string $modelLabel      = 'Supplier';
    protected static ?int    $navigationSort  = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Data Perusahaan')
                ->schema([
                    Forms\Components\TextInput::make('company_name')
                        ->label('Nama Perusahaan')
                        ->required()
                        ->maxLength(200),

                    Forms\Components\TextInput::make('npwp')
                        ->label('NPWP')
                        ->maxLength(20)
                        ->nullable()
                        ->unique(ignoreRecord: true)
                        ->placeholder('00.000.000.0-000.000'),

                    Forms\Components\TextInput::make('address')
                        ->label('Alamat')
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('city')
                        ->label('Kota')
                        ->required(),

                    Forms\Components\TextInput::make('province')
                        ->label('Provinsi')
                        ->nullable(),
                ])->columns(2),

            Section::make('Data PIC (Person In Charge)')
                ->schema([
                    Forms\Components\TextInput::make('pic_name')
                        ->label('Nama PIC')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('pic_phone')
                        ->label('Telepon PIC')
                        ->required()
                        ->tel()
                        ->maxLength(20),
                ])->columns(2),

            Section::make('Kategori & Status')
                ->schema([
                    Forms\Components\Select::make('categories')
                        ->label('Kategori Produk/Jasa')
                        ->relationship('categories', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'aktif'     => 'Aktif',
                            'nonaktif'  => 'Non-Aktif',
                            'blacklist' => 'Blacklist',
                        ])
                        ->required()
                        ->default('aktif')
                        ->live(), // Reactive untuk show/hide alasan blacklist

                    Forms\Components\Textarea::make('blacklist_reason')
                        ->label('Alasan Blacklist')
                        ->rows(3)
                        ->visible(fn (Forms\Get $get) => $get('status') === 'blacklist')
                        ->nullable(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2)
                        ->columnSpanFull()
                        ->nullable(),
                ])->columns(2),

            Section::make('Akun Portal Supplier')
                ->description('Data login untuk supplier portal (Vue)')
                ->schema([
                    Forms\Components\TextInput::make('user.name')
                        ->label('Nama User')
                        ->required()
                        ->dehydrated(false), // Handle via afterCreate

                    Forms\Components\TextInput::make('user.email')
                        ->label('Email Login')
                        ->email()
                        ->required()
                        ->unique('users', 'email', ignoreRecord: true)
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('user.password')
                        ->label('Password')
                        ->password()
                        ->minLength(8)
                        ->dehydrated(false)
                        ->visibleOn('create')
                        ->required()
                        ->helperText('Minimal 8 karakter'),
                ])->columns(2)
                  ->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Nama Perusahaan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('city')
                    ->label('Kota')
                    ->searchable(),

                Tables\Columns\TextColumn::make('pic_name')
                    ->label('PIC')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'aktif',
                        'warning' => 'nonaktif',
                        'danger'  => 'blacklist',
                    ]),

                // Rating bintang
                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . ' ⭐')
                    ->sortable(),

                Tables\Columns\TextColumn::make('win_rate')
                    ->label('Win Rate')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . '%')
                    ->sortable()
                    ->color(fn ($state) => $state >= 50 ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('total_po')
                    ->label('Total PO')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->limit(2),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'aktif'     => 'Aktif',
                        'nonaktif'  => 'Non-Aktif',
                        'blacklist' => 'Blacklist',
                    ]),

                Tables\Filters\SelectFilter::make('categories')
                    ->label('Kategori')
                    ->relationship('categories', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat'),
                Tables\Actions\EditAction::make()->label('Edit'),

                // Blacklist/Unblacklist cepat
                Tables\Actions\Action::make('blacklist')
                    ->label('Blacklist')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status !== 'blacklist')
                    ->form([
                        Forms\Components\Textarea::make('blacklist_reason')
                            ->label('Alasan Blacklist')
                            ->required()->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['status' => 'blacklist', 'blacklist_reason' => $data['blacklist_reason']]);
                        Notification::make()->title("Supplier {$record->company_name} di-blacklist.")->danger()->send();
                    }),

                Tables\Actions\Action::make('aktivasi')
                    ->label('Aktifkan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'aktif')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'aktif', 'blacklist_reason' => null]);
                        Notification::make()->title("Supplier {$record->company_name} diaktifkan.")->success()->send();
                    }),
            ])
            ->defaultSort('rating', 'desc')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'view'   => Pages\ViewSupplier::route('/{record}'),
            'edit'   => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}


// =============================================================================
// FILE: app/Filament/Resources/QuotationResource.php
// PURPOSE: Monitoring dan evaluasi penawaran dari supplier.
//          Admin/purchasing bisa lihat, bandingkan, approve penawaran.
// =============================================================================
namespace App\Filament\Resources;

use App\Models\Quotation;
use App\Services\ProcurementService;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class QuotationResource extends Resource
{
    protected static ?string $model           = Quotation::class;
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Pengadaan';
    protected static ?string $navigationLabel = 'Penawaran';
    protected static ?int    $navigationSort  = 2;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'submitted')->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Form ini untuk VIEW saja, data dikirim dari supplier
            Forms\Components\Section::make('Detail Penawaran')
                ->schema([
                    Forms\Components\TextInput::make('quotation_number')->label('No. Penawaran')->disabled(),
                    Forms\Components\TextInput::make('total_amount')->label('Total Nilai (IDR)')->disabled()
                        ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                    Forms\Components\TextInput::make('delivery_days')->label('Estimasi Pengiriman (hari)')->disabled(),
                    Forms\Components\DatePicker::make('valid_until')->label('Berlaku Hingga')->disabled(),
                    Forms\Components\Textarea::make('terms')->label('Syarat & Ketentuan')->disabled()->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('notes')->label('Catatan Supplier')->disabled()->rows(2)->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Review Purchasing')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Ubah Status')
                        ->options([
                            'submitted' => 'Submitted',
                            'review'    => 'Dalam Review',
                            'selected'  => 'Dipilih',
                            'rejected'  => 'Ditolak',
                            'revised'   => 'Revisi',
                        ]),
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Alasan Penolakan / Catatan')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('score')
                        ->label('Skor Evaluasi')
                        ->numeric()
                        ->disabled(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('purchaseRequest.pr_number')
                    ->label('No. PR')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.company_name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Nilai')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_days')
                    ->label('Est. Kirim')
                    ->formatStateUsing(fn ($state) => $state . ' hari'),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Berlaku s/d')
                    ->date('d/m/Y'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'info'    => 'submitted',
                        'warning' => 'review',
                        'success' => 'selected',
                        'danger'  => 'rejected',
                        'gray'    => 'revised',
                    ]),

                Tables\Columns\IconColumn::make('is_best')
                    ->label('Terbaik')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->trueColor('warning'),

                Tables\Columns\TextColumn::make('score')
                    ->label('Skor')
                    ->formatStateUsing(fn ($state) => number_format($state, 1))
                    ->sortable()
                    ->color(fn ($state) => $state >= 80 ? 'success' : ($state >= 60 ? 'warning' : 'danger')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'submitted' => 'Submitted', 'review' => 'Dalam Review',
                        'selected'  => 'Dipilih',   'rejected' => 'Ditolak',
                    ]),
                Tables\Filters\Filter::make('is_best')
                    ->label('Hanya Best Quotation')
                    ->query(fn ($q) => $q->where('is_best', true)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat'),
                Tables\Actions\EditAction::make()->label('Review'),

                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['submitted', 'review']))
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Penawaran Ini?')
                    ->action(function ($record) {
                        app(ProcurementService::class)->approveQuotation($record);
                        Notification::make()->title('Penawaran disetujui.')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\QuotationResource\Pages\ListQuotations::route('/'),
            'view'  => \App\Filament\Resources\QuotationResource\Pages\ViewQuotation::route('/{record}'),
            'edit'  => \App\Filament\Resources\QuotationResource\Pages\EditQuotation::route('/{record}/edit'),
        ];
    }
}

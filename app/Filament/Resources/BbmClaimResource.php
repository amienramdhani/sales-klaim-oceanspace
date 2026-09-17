<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BbmClaimResource\Pages;
use App\Models\Branch;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\FuelPrice;
use App\Services\ClaimExcelExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BbmClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'PENGAJUAN KLAIM';

    protected static ?string $modelLabel = 'Klaim BBM';

    protected static ?string $pluralModelLabel = 'Pengajuan Klaim BBM';

    protected static ?int $navigationSort = 1;

    /**
     * ASM, RGM, Sales, Admin, SuperAdmin dapat mengakses menu ini
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isJejen()) return false;
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isAsm() || $user->isRgm() || $user->isSales();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isFinance()) return false;
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()
            ->where(function ($q) {
                $q->where('claim_category', 'bbm')
                  ->orWhere('claim_type', 'like', '%BBM%')
                  ->orWhereNotNull('bbm_photo_combined')
                  ->orWhereNotNull('bbm_photo_before');
            })
            ->with(['employee.role', 'employee.positionModel', 'branch']);

        if ($user && $user->isFieldUser() && !$user->isAdmin() && !$user->isSuperAdmin()) {
            return $query->ownSubmissionsOnly($user);
        }

        return $query->visibleToUser($user);
    }

    public static function form(Form $form): Form
    {
        $user = auth()->user();
        if ($user && $user->isFieldUser() && !$user->isAdmin() && !$user->isSuperAdmin() && !$user->isFinance()) {
            return $form
                ->schema([
                    Forms\Components\Hidden::make('user_id')->default(fn () => auth()->id()),
                    Forms\Components\Hidden::make('claim_category')->default('bbm'),
                    Forms\Components\Hidden::make('claim_type')->default('Klaim BBM'),
                    Forms\Components\Hidden::make('approval_status')->default('DIAJUKAN'),
                    Forms\Components\Hidden::make('disbursement_status')->default('Belum Dicairkan'),
                    Forms\Components\Hidden::make('employee_id')
                        ->default(fn () => auth()->user()?->getEffectiveEmployeeId())
                        ->visible(fn () => !empty(auth()->user()?->getEffectiveEmployeeId())),

                    Forms\Components\Section::make('Pengajuan Klaim BBM')
                        ->description('Silakan kirimkan foto dan keterangan data pengisian BBM. Data akan dicek dan diverifikasi oleh Admin.')
                        ->icon('heroicon-o-truck')
                        ->schema([
                            Forms\Components\Select::make('employee_id')
                                ->label('Nama Karyawan / Pemohon')
                                ->relationship('employee', 'name')
                                ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->name} ({$record->position_name})")
                                ->default(fn () => auth()->user()?->getEffectiveEmployeeId())
                                ->visible(fn () => empty(auth()->user()?->getEffectiveEmployeeId()))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpanFull(),

                            // 3. Tanggal
                            Forms\Components\DatePicker::make('claim_date')
                                ->label('Tanggal Pengisian BBM')
                                ->default(now())
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->columnSpan(['sm' => 1]),

                            // 5. Jenis BBM
                            Forms\Components\Select::make('fuel_type')
                                ->label('Jenis BBM')
                                ->options([
                                    'Pertalite' => 'Pertalite',
                                    'Pertamax' => 'Pertamax',
                                    'Solar' => 'Solar / Dexlite',
                                ])
                                ->default('Pertalite')
                                ->required()
                                ->native(false)
                                ->columnSpan(['sm' => 1]),

                            // 2. KM
                            Forms\Components\TextInput::make('fuel_start_km')
                                ->label('KM (Speedometer / Odometer)')
                                ->numeric()
                                ->suffix('KM')
                                ->placeholder('Contoh: 12540')
                                ->helperText('Angka KM speedometer saat pengisian bensin.')
                                ->required()
                                ->columnSpan(['sm' => 1]),

                            // 4. Nominal Pengisian
                            Forms\Components\TextInput::make('amount')
                                ->label('Nominal Pengisian (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Contoh: 50000')
                                ->helperText('Nominal rupiah sesuai struk / pembelian BBM.')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    $set('fuel_base_amount', $state);
                                })
                                ->columnSpan(['sm' => 1]),

                            Forms\Components\Hidden::make('fuel_base_amount'),

                            // 1. Foto
                            Forms\Components\FileUpload::make('photos')
                                ->label('Foto Nota BBM & Speedometer')
                                ->helperText('Upload foto struk SPBU dan foto speedometer/odometer. Format JPG/PNG (bisa lebih dari 1 foto).')
                                ->image()
                                ->imageResizeMode('contain')
                                ->imageResizeTargetWidth('1920')
                                ->imageResizeTargetHeight('1920')
                                ->imageResizeUpscale(false)
                                ->multiple()
                                ->disk('public')
                                ->directory('claim-photos')
                                ->visibility('public')
                                ->openable()
                                ->downloadable()
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->columns(['sm' => 2, 'lg' => 4]),
                ]);
        }

        // Tampilan Lengkap Admin / Super Admin / Finance
        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')->default(fn () => auth()->id()),
                // 1. FORM _UID & STATUS (Hanya untuk Admin / Finance / Super Admin)
                Forms\Components\Section::make('_UID EXTERNAL & STATUS PENGAJUAN')
                    ->description('Nomor referensi ID Form External dan status approval sistem. Terekam di awal formulir.')
                    ->icon('heroicon-o-identification')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin() || auth()->user()?->isFinance())
                    ->schema([
                        Forms\Components\TextInput::make('_uid')
                            ->label('_UID (ID Form External)')
                            ->placeholder('Contoh: UID-BBM-2026-001')
                            ->helperText('Setelah _UID terisi, pengajuan otomatis masuk ke antrean Finance.')
                            ->columnSpan(1),

                        Forms\Components\Select::make('approval_status')
                            ->label('Status Approval')
                            ->options([
                                'DRAFT' => 'DRAFT',
                                'DIAJUKAN' => 'DIAJUKAN',
                                'ACC_ASM' => 'ACC_ASM',
                                'ACC_RGM' => 'ACC_RGM',
                                'ACC_PAK_JEJEN' => 'ACC_PAK_JEJEN',
                                'DISETUJUI' => 'DISETUJUI',
                                'DITOLAK' => 'DITOLAK',
                            ])
                            ->default('DIAJUKAN')
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Select::make('disbursement_status')
                            ->label('Status Pencairan')
                            ->options([
                                'Belum Dicairkan' => 'Belum Dicairkan',
                                'Sudah Dicairkan' => 'Sudah Dicairkan',
                            ])
                            ->default('Belum Dicairkan')
                            ->required()
                            ->columnSpan(1),
                    ])->columns(3)
                    ->extraAttributes(['style' => 'background: #6dbbefff; border-radius: 12px; padding: 1.2rem;']),
                    

                // 2. Informasi Pemohon & Cabang
                Forms\Components\Section::make('Informasi Pemohon & Cabang')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Pemohon / User')
                            ->relationship('employee', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->name} ({$record->position_name})")
                            ->default(fn () => auth()->user()?->getEffectiveEmployeeId())
                            ->disabled(fn () => !auth()->user()?->isAdmin() && !auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\DatePicker::make('claim_date')
                            ->label('Tanggal Pengajuan')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('branch_id')
                            ->label('Branch / Cabang')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $branch = Branch::find($state);
                                    if ($branch) {
                                        $set('brand', $branch->brand);
                                        $set('reffnote', $branch->reffnote);
                                        $set('city', $branch->city);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('brand')
                            ->label('Brand')
                            ->placeholder('Contoh: REALME / OPPO'),

                        Forms\Components\TextInput::make('reffnote')
                            ->label('Reffnote')
                            ->placeholder('Contoh: REALME PURWOKERTO'),

                        Forms\Components\TextInput::make('city')
                            ->label('Kota Utama')
                            ->placeholder('Contoh: Purwokerto / Cirebon'),
                    ])->columns(3),

                // 3. Transaksi BBM (Multi-Transaksi dengan Upload Foto Before & After per Item)
                Forms\Components\Section::make('Daftar Transaksi BBM & Odometer (Bisa Multi-Transaksi)')
                    ->description('Tambahkan 1 atau lebih transaksi pengisian BBM. Setiap transaksi memiliki perhitungan harga/literan serta upload foto odometer sebelum & sesudah.')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Rincian Transaksi Pengisian BBM')
                            ->schema([
                                Forms\Components\Hidden::make('claim_type')->default('BBM'),

                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\DatePicker::make('receipt_date')
                                            ->label('Tanggal Nota BBM')
                                            ->default(now())
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->required(),

                                        Forms\Components\Select::make('vehicle_type')
                                            ->label('Jenis Kendaraan')
                                            ->options([
                                                'Mobil' => 'Mobil',
                                                'Motor' => 'Motor',
                                            ])
                                            ->default('Mobil')
                                            ->reactive(),

                                        Forms\Components\Select::make('fuel_type')
                                            ->label('Jenis BBM')
                                            ->options([
                                                'Pertalite' => 'Pertalite',
                                                'Pertamax' => 'Pertamax (Dihitung Sesuai Harga Pertalite)',
                                                'Solar' => 'Solar / Dexlite',
                                            ])
                                            ->default('Pertalite')
                                            ->reactive(),

                                        Forms\Components\TextInput::make('note')
                                            ->label('Lokasi SPBU / Nomor SPBU')
                                            ->placeholder('Contoh: SPBU 44.531 Tuparev Cirebon'),
                                    ]),

                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('fuel_start_km')
                                            ->label('KM Awal (Sebelum Isi Bensin)')
                                            ->numeric()
                                            ->suffix('KM')
                                            ->placeholder('Contoh: 12540')
                                            ->helperText('Angka odometer sebelum isi bensin.'),

                                        Forms\Components\TextInput::make('fuel_base_amount')
                                            ->label('Nominal Pembelian Nota (Rp)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                self::recalculateBbmItem($get, $set);
                                            }),

                                        Forms\Components\TextInput::make('fuel_pertamax_price')
                                            ->label('Harga Pertamax/L (Rp)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->default(fn () => FuelPrice::getPertamaxPrice())
                                            ->visible(fn (Forms\Get $get) => $get('fuel_type') === 'Pertamax')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                                self::recalculateBbmItem($get, $set);
                                            }),

                                        Forms\Components\TextInput::make('fuel_pertalite_price')
                                            ->label('Harga Pertalite/L (Rp)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->default(fn () => FuelPrice::getPertalitePrice())
                                            ->visible(fn (Forms\Get $get) => $get('fuel_type') === 'Pertamax')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                                self::recalculateBbmItem($get, $set);
                                            }),

                                        Forms\Components\TextInput::make('fuel_liters')
                                            ->label('Volume Didapat (Liter)')
                                            ->numeric()
                                            ->suffix('Liter')
                                            ->disabled()
                                            ->dehydrated(),

                                        Forms\Components\TextInput::make('amount')
                                            ->label('Nominal Klaim Disetujui (Rp)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->required()
                                            ->readOnly()
                                            ->dehydrated()
                                            ->helperText('Otomatis dihitung sesuai nominal pembelian nota / konversi Pertalite Master Data'),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\FileUpload::make('bbm_photo_before')
                                            ->label('Foto Odometer Sebelum (Before)')
                                            ->image()
                                            ->imageResizeMode('contain')
                                            ->imageResizeTargetWidth('1920')
                                            ->imageResizeTargetHeight('1920')
                                            ->imageResizeUpscale(false)
                                            ->disk('public')
                                            ->directory('claim-photos')
                                            ->visibility('public')
                                            ->openable()
                                            ->downloadable()
                                            ->dehydrated(),

                                        Forms\Components\FileUpload::make('bbm_photo_after')
                                            ->label('Foto Odometer Sesudah (After)')
                                            ->image()
                                            ->imageResizeMode('contain')
                                            ->imageResizeTargetWidth('1920')
                                            ->imageResizeTargetHeight('1920')
                                            ->imageResizeUpscale(false)
                                            ->disk('public')
                                            ->directory('claim-photos')
                                            ->visibility('public')
                                            ->openable()
                                            ->downloadable()
                                            ->dehydrated(),
                                    ]),
                            ])
                            ->columns(1)
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Pengisian BBM Lainnya (Multi-Transaksi)')
                            ->reorderable(false)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Total Nominal Keseluruhan Klaim BBM (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->readOnly()
                            ->dehydrated()
                            ->helperText('Total otomatis terakumulasi dari seluruh pengisian BBM.')
                            ->default(0)
                            ->columnSpan(2),
                    ]),

                // 4. Bukti Pengembalian Sisa Budget ke Finance (Opsional jika klaim dibawah budget)
                Forms\Components\Section::make('Pengembalian Sisa Dana ke Finance (Transfer Balik)')
                    ->description('Jika realisasi klaim di bawah budget awal, unggah bukti pengembalian sisa dana ke rekening Finance.')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('return_transfer_amount')
                            ->label('Nominal Ditransfer Balik (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->placeholder('Contoh: 250000'),

                        Forms\Components\FileUpload::make('return_transfer_proof')
                            ->label('Foto / Bukti Transfer Balik')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('return-transfer-proofs')
                            ->visibility('public')
                            ->openable()
                            ->downloadable(),

                        Forms\Components\Textarea::make('return_transfer_notes')
                            ->label('Catatan Pengembalian')
                            ->placeholder('Contoh: Ditransfer via BCA Finance pada tanggal 01/09/2026')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function recalculateBbmItem(Forms\Get $get, Forms\Set $set): void
    {
        $base = (float)$get('fuel_base_amount');
        $fuelType = $get('fuel_type') ?? 'Pertalite';
        $pertamaxPrice = (float)($get('fuel_pertamax_price') ?: FuelPrice::getPertamaxPrice());
        $pertalitePrice = (float)($get('fuel_pertalite_price') ?: FuelPrice::getPertalitePrice());

        if ($fuelType === 'Pertamax' && $pertamaxPrice > 0) {
            $liters = round($base / $pertamaxPrice, 3);
            $converted = round($liters * $pertalitePrice);
            $finalAmount = $converted;
            $set('fuel_liters', $liters);
            $set('amount', $finalAmount);
        } else {
            $baseRate = $pertalitePrice > 0 ? $pertalitePrice : 10000;
            $set('fuel_liters', round($base / $baseRate, 3));
            $finalAmount = $base;
            $set('amount', $finalAmount);
        }

        // Auto sum parent
        $items = $get('../../items') ?? [];
        $total = 0;
        foreach ($items as $it) {
            $total += (float)($it['amount'] ?? 0);
        }
        $set('../../amount', $total > 0 ? $total : $finalAmount);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('claim_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('_uid')
                    ->label('_UID')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Pemohon')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->state(fn (Claim $record) => $record->effective_employee?->name ?? '-')
                    ->description(fn (Claim $record) => $record->effective_employee?->position_name ?? '-'),

                Tables\Columns\TextColumn::make('region')
                    ->label('Region')
                    ->badge()
                    ->color('info')
                    ->state(fn (Claim $record) => $record->region ?: ($record->effective_employee?->region ?: ($record->homebase ?: '-')))
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal Klaim')
                    ->money('IDR')
                    ->weight('bold')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total')),

                Tables\Columns\TextColumn::make('return_transfer_status')
                    ->label('Sisa Dana & Transfer Balik')
                    ->state(fn (Claim $record) => $record->return_transfer_status['label'])
                    ->badge()
                    ->color(fn (Claim $record) => $record->return_transfer_status['color']),

                Tables\Columns\TextColumn::make('fuel_compliance')
                    ->label('Ketentuan BBM')
                    ->state(fn (Claim $record) => $record->fuel_compliance['label'])
                    ->badge()
                    ->color(fn (Claim $record) => $record->fuel_compliance['color']),

                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'DIAJUKAN' => 'warning',
                        'SEDANG_DIREVISI' => 'info',
                        'ACC_ASM' => 'info',
                        'ACC_RGM' => 'primary',
                        'ACC_PAK_JEJEN' => 'indigo',
                        'DISETUJUI' => 'success',
                        'DITOLAK_FINANCE' => 'danger',
                        'DITOLAK' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (Claim $record) => in_array($record->approval_status, ['DITOLAK', 'DITOLAK_FINANCE', 'SEDANG_DIREVISI']) && !empty($record->rejection_reason) ? "Catatan: {$record->rejection_reason}" : null),

                Tables\Columns\TextColumn::make('disbursement_status')
                    ->label('Pencairan')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sudah Dicairkan' ? 'success' : 'danger'),
            ])
            ->defaultSort('claim_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('month')
                    ->label('Bulan')
                    ->options([
                        '1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April',
                        '5' => 'Mei', '6' => 'Juni', '7' => 'Juli', '8' => 'Agustus',
                        '9' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                    ])
                    ->query(fn (Builder $query, array $data) => !empty($data['value']) ? $query->whereMonth('claim_date', $data['value']) : $query),

                Tables\Filters\SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(['2024' => '2024', '2025' => '2025', '2026' => '2026', '2027' => '2027'])
                    ->query(fn (Builder $query, array $data) => !empty($data['value']) ? $query->whereYear('claim_date', $data['value']) : $query),

                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Pemohon')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('region')
                    ->label('Region')
                    ->options(function () {
                        $user = auth()->user();
                        if ($user && $user->isAdmin()) {
                            $managed = $user->getManagedRegionsList();
                            if (!empty($managed)) {
                                return array_combine($managed, $managed);
                            }
                        }
                        return [
                            'CIREBON' => 'CIREBON',
                            'JABO' => 'JABO',
                            'JABODETABEK' => 'JABODETABEK',
                            'JATENG' => 'JATENG',
                            'JATIM' => 'JATIM',
                            'JOGJA' => 'JOGJA',
                            'PURWOKERTO' => 'PURWOKERTO',
                            'JAKARTA' => 'JAKARTA',
                            'SUMATERA' => 'SUMATERA',
                            'SUMATERA BARAT' => 'SUMATERA BARAT',
                            'SUMATRA UTARA & ACEH' => 'SUMATRA UTARA & ACEH',
                            'SULAWESI' => 'SULAWESI',
                            'LAMPUNG & BENGKULU' => 'LAMPUNG & BENGKULU',
                        ];
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $val = $data['value'];
                            $query->where(function ($q) use ($val) {
                                $q->where('claims.region', 'LIKE', "%{$val}%")
                                  ->orWhere('claims.homebase', 'LIKE', "%{$val}%")
                                  ->orWhere('claims.city', 'LIKE', "%{$val}%")
                                  ->orWhereHas('employee', fn ($eq) => $eq->where('region', 'LIKE', "%{$val}%")->orWhere('homebase', 'LIKE', "%{$val}%"))
                                  ->orWhereHas('user', fn ($uq) => $uq->where('region', 'LIKE', "%{$val}%")->orWhere('homebase', 'LIKE', "%{$val}%"));
                            });
                        }
                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('has_uid')
                    ->label('Status _UID')
                    ->options([
                        'yes' => 'Sudah Ada _UID',
                        'no' => 'Belum Ada _UID',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (($data['value'] ?? null) === 'yes') return $query->whereNotNull('_uid');
                        if (($data['value'] ?? null) === 'no') return $query->whereNull('_uid');
                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('fuel_type')
                    ->label('Jenis BBM')
                    ->options([
                        'Pertalite' => 'Pertalite',
                        'Pertamax' => 'Pertamax',
                        'Solar' => 'Solar',
                    ]),

                Tables\Filters\SelectFilter::make('vehicle_type')
                    ->label('Kendaraan')
                    ->options([
                        'Mobil' => 'Mobil',
                        'Motor' => 'Motor',
                    ]),

                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Status Approval')
                    ->options([
                        'DRAFT' => 'DRAFT',
                        'DIAJUKAN' => 'DIAJUKAN (Menunggu ACC)',
                        'SEDANG_DIREVISI' => 'SEDANG DIREVISI',
                        'ACC_ASM' => 'ACC_ASM (Menunggu RGM)',
                        'ACC_RGM' => 'ACC_RGM (Menunggu Pak Jejen)',
                        'ACC_PAK_JEJEN' => 'ACC_PAK_JEJEN (Menunggu Verifikasi Admin)',
                        'DISETUJUI' => 'DISETUJUI (Siap Dicairkan Finance)',
                        'DITOLAK_FINANCE' => 'DITOLAK FINANCE (Perlu Revisi Admin)',
                        'DITOLAK' => 'DITOLAK',
                    ]),

                Tables\Filters\SelectFilter::make('disbursement_status')
                    ->label('Status Pencairan')
                    ->options([
                        'Belum Dicairkan' => 'Belum Dicairkan',
                        'Sudah Dicairkan' => 'Sudah Dicairkan',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_bbm_excel')
                    ->label('Export Rekapan Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(function (\Filament\Tables\Contracts\HasTable $livewire) {
                        $claims = $livewire->getFilteredTableQuery()
                            ->with(['employee.role', 'branch'])
                            ->orderBy('claim_date', 'asc')
                            ->get();

                        return app(\App\Services\ClaimExcelExportService::class)->exportBbmRecapExcel($claims);
                    }),
            ])
            ->actions([
                \App\Filament\Actions\UploadReturnTransferProofAction::make(),
                \App\Filament\Actions\ViewReturnTransferProofAction::make(),
                \App\Filament\Actions\ViewTransferProofAction::make(),

                // 1. Approval Atasan: ACC ASM
                Tables\Actions\Action::make('acc_asm')
                    ->label('ACC ASM')
                    ->icon('heroicon-o-check')
                    ->color('info')
                    ->visible(fn (Claim $record) => $record->approval_status === 'DIAJUKAN' && Claim::isAsmSupervisorOf(auth()->user(), $record))
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Klaim (ASM)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim BBM ini untuk diteruskan ke RGM?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_ASM',
                            'approved_by_asm_id' => $user?->id,
                            'approved_by_asm_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan klaim BBM disetujui ASM! Diteruskan ke RGM.')->info()->send();
                    }),

                Tables\Actions\Action::make('reject_asm')
                    ->label('Tolak ASM')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Claim $record) => $record->approval_status === 'DIAJUKAN' && Claim::isAsmSupervisorOf(auth()->user(), $record))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan / Catatan Perbaikan')
                            ->placeholder('Tuliskan hal yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan klaim BBM ditolak ASM.')->warning()->send();
                    }),

                // 2. Approval Atasan: ACC RGM
                Tables\Actions\Action::make('acc_rgm')
                    ->label('ACC RGM')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!Claim::isRgmSupervisorOf($user, $record)) return false;
                        $applicantEmp = $record->effective_employee;
                        $isApplicantAsm = ($applicantEmp?->isAsm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'ASM'));
                        return $isApplicantAsm ? ($record->approval_status === 'DIAJUKAN') : ($record->approval_status === 'ACC_ASM');
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Klaim (RGM)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim BBM ini untuk diteruskan ke Head of Sales (Pak Jejen)?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_RGM',
                            'approved_by_rgm_id' => $user?->id,
                            'approved_by_rgm_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan klaim BBM disetujui RGM! Diteruskan ke Head of Sales (Pak Jejen).')->primary()->send();
                    }),

                Tables\Actions\Action::make('reject_rgm')
                    ->label('Tolak RGM')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!Claim::isRgmSupervisorOf($user, $record)) return false;
                        $applicantEmp = $record->effective_employee;
                        $isApplicantAsm = ($applicantEmp?->isAsm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'ASM'));
                        return $isApplicantAsm ? ($record->approval_status === 'DIAJUKAN') : ($record->approval_status === 'ACC_ASM');
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan / Catatan Perbaikan')
                            ->placeholder('Tuliskan hal yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan klaim BBM ditolak RGM.')->warning()->send();
                    }),

                // 3. Approval Atasan: ACC Pak Jejen (Head of Sales)
                Tables\Actions\Action::make('acc_jejen')
                    ->label('ACC Pak Jejen')
                    ->icon('heroicon-o-star')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!Claim::isJejenApproverOf($user, $record)) return false;
                        $applicantEmp = $record->effective_employee;
                        $isApplicantRgm = ($applicantEmp?->isRgm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'RGM'));
                        return $isApplicantRgm ? ($record->approval_status === 'DIAJUKAN') : ($record->approval_status === 'ACC_RGM');
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Klaim (Head of Sales)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim BBM ini?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_PAK_JEJEN',
                            'approved_by_jejen_id' => $user?->id,
                            'approved_by_jejen_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan klaim BBM disetujui Pak Jejen! Siap diverifikasi Admin untuk Finance.')->success()->send();
                    }),

                Tables\Actions\Action::make('reject_jejen')
                    ->label('Tolak Pak Jejen')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!Claim::isJejenApproverOf($user, $record)) return false;
                        $applicantEmp = $record->effective_employee;
                        $isApplicantRgm = ($applicantEmp?->isRgm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'RGM'));
                        return $isApplicantRgm ? ($record->approval_status === 'DIAJUKAN') : ($record->approval_status === 'ACC_RGM');
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan / Catatan Perbaikan')
                            ->placeholder('Tuliskan hal yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan klaim BBM ditolak Pak Jejen.')->warning()->send();
                    }),

                // 4. Admin Verifikasi & Setujui -> Kirim ke Finance
                Tables\Actions\Action::make('admin_verify_approve')
                    ->label('Verifikasi & Kirim ke Finance')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) return false;
                        return $record->approval_status === 'ACC_PAK_JEJEN';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi & Kirim ke Finance')
                    ->modalDescription('Pastikan seluruh bukti BBM dan persetujuan atasan telah sah. Klaim akan langsung diteruskan ke Finance untuk pencairan dana.')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'admin_approved_by_id' => $user?->id,
                            'admin_approved_at' => now(),
                        ]);
                        Notification::make()->title('Klaim BBM telah diverifikasi & disetujui Admin! Otomatis diteruskan ke Finance untuk pencairan.')->success()->send();
                    }),

                // 4.1 Admin Tolak Sebelum ke Finance
                Tables\Actions\Action::make('admin_reject')
                    ->label('Tolak Admin')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) return false;
                        return in_array($record->approval_status, ['ACC_PAK_JEJEN', 'DIAJUKAN']);
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan Admin')
                            ->placeholder('Tuliskan rincian yang perlu diperbaiki pemohon...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan klaim BBM ditolak oleh Admin.')->warning()->send();
                    }),

                // 5. Admin Kirim Ulang ke Finance setelah revisi data
                Tables\Actions\Action::make('admin_resubmit_to_finance')
                    ->label('Kirim Ulang ke Finance')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) return false;
                        return $record->approval_status === 'DITOLAK_FINANCE';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Ulang Revisi Klaim ke Finance')
                    ->modalDescription('Apakah data revisi BBM sudah benar dan siap diajukan kembali ke Finance untuk pencairan?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'admin_approved_by_id' => $user?->id,
                            'admin_approved_at' => now(),
                        ]);
                        Notification::make()->title('Klaim BBM hasil revisi berhasil dikirimkan kembali ke Finance!')->success()->send();
                    }),

                // 6. Pemohon Ajukan Kembali (jika ditolak atasan)
                Tables\Actions\Action::make('resubmit_claim')
                    ->label(fn (Claim $record) => in_array($record->approval_status, ['DITOLAK', 'SEDANG_DIREVISI']) ? 'Ajukan Kembali' : 'Ajukan Klaim')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        $isApplicant = ($record->user_id === $user->id || $record->employee_id == $user->getEffectiveEmployeeId());
                        return in_array($record->approval_status, ['DRAFT', 'DITOLAK', 'SEDANG_DIREVISI']) && ($isApplicant || $user->isAdmin() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Ajukan Kembali Pengajuan Klaim BBM')
                    ->modalDescription('Pastikan foto odometer dan struk BBM telah diperbaiki sesuai catatan sebelum diajukan kembali.')
                    ->action(function (Claim $record) {
                        $record->update([
                            'approval_status' => 'DIAJUKAN',
                            'approved_by_asm_id' => null,
                            'approved_by_asm_at' => null,
                            'approved_by_rgm_id' => null,
                            'approved_by_rgm_at' => null,
                            'approved_by_jejen_id' => null,
                            'approved_by_jejen_at' => null,
                            'admin_approved_by_id' => null,
                            'admin_approved_at' => null,
                        ]);
                        Notification::make()->title('Klaim BBM berhasil diajukan kembali ke atasan!')->success()->send();
                    }),

                // Download Actions
                Tables\Actions\Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn (Claim $record) => app(\App\Services\ClaimPdfExportService::class)->exportBbmSinglePdf($record)),

                Tables\Actions\Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(fn (Claim $record) => app(\App\Services\ClaimExcelExportService::class)->exportSingleClaim($record)),

                Tables\Actions\Action::make('export_word')
                    ->label('Export Word')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->action(fn (Claim $record) => app(\App\Services\BbmClaimWordExportService::class)->export($record)),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBbmClaims::route('/'),
            'create' => Pages\CreateBbmClaim::route('/create'),
            'edit' => Pages\EditBbmClaim::route('/{record}/edit'),
        ];
    }
}

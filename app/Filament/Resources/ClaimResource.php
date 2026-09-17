<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClaimResource\Pages;
use App\Models\Branch;
use App\Models\BudgetType;
use App\Models\Claim;
use App\Models\Employee;
use App\Services\ClaimExcelExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'PENGAJUAN KLAIM';

    protected static ?string $modelLabel = 'Semua Pengajuan Klaim';

    protected static ?string $pluralModelLabel = 'Semua Pengajuan Klaim';

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        if (auth()->user()?->isFinance()) {
            return 'Semua Klaim yang Diajukan';
        }
        return 'Semua Pengajuan Klaim';
    }

    public static function getNavigationGroup(): ?string
    {
        if (auth()->user()?->isFinance()) {
            return 'MENU FINANCE';
        }
        return 'PENGAJUAN KLAIM';
    }

    /**
     * Admin, SuperAdmin, dan Finance dapat melihat semua pengajuan klaim
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isFinance();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance()) return false;
        return true;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance()) return false;
        return true;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance()) return false;
        return $user->isSuperAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['employee.role', 'employee.positionModel', 'branch', 'claimPeriod.employee'])
            ->visibleToUser();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->disabled(fn () => auth()->user()?->isFinance())
            ->schema([
                Forms\Components\Hidden::make('user_id')->default(fn () => auth()->id()),
                // TOP SECTION: _UID EXTERNAL & STATUS PENGAJUAN
                Forms\Components\Section::make('_UID EXTERNAL & STATUS PENGAJUAN')
                    ->description('Nomor referensi ID Form External dan status approval sistem. Terekam di awal formulir.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Forms\Components\TextInput::make('_uid')
                            ->label('_UID (ID Form External)')
                            ->placeholder('Contoh: UID-KLAIM-2026-001')
                            ->helperText('Setelah _UID terisi, pengajuan otomatis masuk ke antrean Finance.')
                            ->columnSpan(1),

                        Forms\Components\Select::make('approval_status')
                            ->label('Status Approval')
                            ->options([
                                'DRAFT' => 'DRAFT',
                                'DIAJUKAN' => 'DIAJUKAN',
                                'SEDANG_DIREVISI' => 'SEDANG DIREVISI',
                                'ACC_ASM' => 'ACC_ASM',
                                'ACC_RGM' => 'ACC_RGM',
                                'ACC_PAK_JEJEN' => 'ACC_PAK_JEJEN',
                                'DISETUJUI' => 'DISETUJUI',
                                'DITOLAK' => 'DITOLAK',
                            ])
                            ->default('DRAFT')
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

                Forms\Components\Section::make('Informasi Pemohon & Cabang')
                    ->description('Pilih user/pemohon klaim dan cabang terkait')
                    ->icon('heroicon-o-user')
                    ->schema([
                        // 1. Pemohon
                        Forms\Components\Select::make('employee_id')
                            ->label('Pemohon / User (Jabatan/Role)')
                            ->relationship('employee', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->name} ({$record->position_name})")
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $emp = Employee::with(['role', 'positionModel'])->find($state);
                                    if ($emp) {
                                        $set('homebase', $emp->homebase ?? 'Purwokerto');
                                        $mealRate = $emp->role?->meal_allowance_per_day ?? 0;
                                        $tollRate = $emp->role?->toll_allowance ?? 500000;
                                        $fuelRate = $emp->role?->fuel_budget_default ?? 0;
                                        $rentalRate = $emp->role?->car_rental_budget_default ?? 0;

                                        $days = 1;
                                        $set('meal_allowance', (float)$mealRate * $days);
                                        $set('lodging_allowance', 0);
                                        $set('toll_cost', (float)$tollRate);
                                        $set('fuel_cost', (float)$fuelRate);
                                        $set('car_rental_cost', (float)$rentalRate);
                                    }
                                }
                            })
                            ->required(),

                        // 2. Tanggal pengajuan
                        Forms\Components\DatePicker::make('claim_date')
                            ->label('Tanggal Pengajuan')
                            ->default(now())
                            ->required(),

                        // 3. Branch
                        Forms\Components\Select::make('branch_id')
                            ->label('Pilih Branch / Cabang')
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

                        // 4. Brand
                        Forms\Components\TextInput::make('brand')
                            ->label('Brand')
                            ->placeholder('Contoh: REALME / OPPO'),

                        // 5. Reffnote
                        Forms\Components\TextInput::make('reffnote')
                            ->label('Reffnote')
                            ->placeholder('Contoh: REALME PURWOKERTO'),

                        // 6. Kota Transaksi
                        Forms\Components\TextInput::make('city')
                            ->label('Kota Utama')
                            ->placeholder('Contoh: Purwokerto / Cilacap / Semarang'),
                    ])->columns(3),

                // SECTION PERJALANAN DINAS (PERDIN)
                Forms\Components\Section::make('Formulir Perjalanan Dinas (Perdin)')
                    ->description('Aktifkan jika pengajuan ini merupakan klaim Perjalanan Dinas dengan ketentuan tarif harian (Uang Makan, Penginapan, Tol, BBM, Sewa Mobil, Service)')
                    ->schema([
                        Forms\Components\Toggle::make('is_perdin')
                            ->label('Jenis Klaim: Perjalanan Dinas')
                            ->reactive()
                            ->helperText('Perjalanan dinas berlaku dengan jarak minimal 80 km dari kota homebase.'),

                        Forms\Components\Grid::make(3)
                            ->visible(fn (Forms\Get $get) => (bool)$get('is_perdin'))
                            ->schema([
                                Forms\Components\TextInput::make('homebase')
                                    ->label('Kota Asal (Homebase)')
                                    ->default('Purwokerto')
                                    ->required(fn (Forms\Get $get) => (bool)$get('is_perdin'))
                                    ->placeholder('Contoh: Purwokerto'),

                                Forms\Components\TextInput::make('destination_city')
                                    ->label('Kota Tujuan Dinas')
                                    ->required(fn (Forms\Get $get) => (bool)$get('is_perdin'))
                                    ->placeholder('Contoh: Majenang / Cilacap / Semarang'),

                                Forms\Components\TextInput::make('distance_km')
                                    ->label('Jarak Perjalanan (KM)')
                                    ->numeric()
                                    ->suffix('KM')
                                    ->required(fn (Forms\Get $get) => (bool)$get('is_perdin'))
                                    ->helperText('Ketentuan: Minimal jarak 80 km dari homebase')
                                    ->rule(function () {
                                        return function (string $attribute, $value, \Closure $fail) {
                                            if ($value < 80) {
                                                $fail('Minimal jarak perjalanan dinas adalah 80 km dari homebase.');
                                            }
                                        };
                                    }),

                                Forms\Components\TextInput::make('days_count')
                                    ->label('Jumlah Hari Dinas')
                                    ->numeric()
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                        $empId = $get('employee_id');
                                        if ($empId) {
                                            $emp = Employee::with('role')->find($empId);
                                            $rate = $emp?->role?->meal_allowance_per_day ?? 0;
                                            $set('meal_allowance', (float)$rate * (int)$state);
                                        }
                                    }),

                                Forms\Components\TextInput::make('nights_count')
                                    ->label('Jumlah Malam Menginap')
                                    ->numeric()
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                        $empId = $get('employee_id');
                                        if ($empId) {
                                            $emp = Employee::with('role')->find($empId);
                                            $rate = $emp?->role?->lodging_allowance_per_night ?? 0;
                                            $set('lodging_allowance', (float)$rate * (int)$state);
                                        }
                                    }),

                                Forms\Components\TextInput::make('meal_allowance')
                                    ->label('Uang Makan (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->helperText('RGM: 100k/hr, ASM: 75k/hr, ASC: 50k/hr, DSF: 35k/hr'),

                                Forms\Components\TextInput::make('lodging_allowance')
                                    ->label('Uang Penginapan (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->helperText('RGM: 300k/mlm, ASM: 250k/mlm, ASC: 150-200k, DSF: 150k'),

                                Forms\Components\TextInput::make('toll_cost')
                                    ->label('Uang Tol (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->helperText('Standar plafon Rp 500.000 by klaim'),

                                Forms\Components\TextInput::make('fuel_cost')
                                    ->label('Budget Bensin / BBM (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->helperText('Dapat disesuaikan per daerah ASM'),

                                Forms\Components\TextInput::make('car_rental_cost')
                                    ->label('Kompensasi Sewa Mobil (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->helperText('Disesuaikan per daerah ASM'),

                                Forms\Components\TextInput::make('service_cost')
                                    ->label('Kompensasi Service (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->helperText('Mobil Rp 2jt / Motor Rp 500rb per 3 Bulan'),
                            ]),
                    ]),

                // SECTION KETENTUAN BBM MOBIL & MOTOR (PERTALITE & +5 RIBU)
                Forms\Components\Section::make('Ketentuan Khusus BBM (Mobil / Motor)')
                    ->description('Ketentuan Perusahaan: BBM Mobil WAJIB jenis Pertalite dan nominal pengajuan WAJIB dilebihkan Rp 5.000 (contoh: Nota Rp 200.000 maka klaim menjadi Rp 205.000). Pengajuan jenis Pertamax TIDAK DIPERBOLEHKAN.')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Select::make('vehicle_type')
                                    ->label('Jenis Kendaraan')
                                    ->options([
                                        'Mobil' => 'Mobil (Wajib Pertalite & Tambah Rp 5.000)',
                                        'Motor' => 'Motor',
                                    ])
                                    ->default('Mobil')
                                    ->reactive()
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                        $base = (float)$get('fuel_base_amount');
                                        if ($state === 'Mobil' && $base > 0) {
                                            $set('fuel_extra_amount', 5000);
                                            $set('amount', $base + 5000);
                                        } else {
                                            $set('fuel_extra_amount', 0);
                                            if ($base > 0) $set('amount', $base);
                                        }
                                    }),

                                Forms\Components\Select::make('fuel_type')
                                    ->label('Jenis Bahan Bakar (BBM)')
                                    ->options([
                                        'Pertalite' => 'Pertalite (Sesuai Ketentuan)',
                                        'Pertamax' => 'Pertamax (DITOLAK - Tidak Diperbolehkan)',
                                        'Solar' => 'Solar / Dexlite',
                                        'Lainnya' => 'Lainnya',
                                    ])
                                    ->default('Pertalite')
                                    ->reactive(),

                                Forms\Components\TextInput::make('fuel_start_km')
                                    ->label('KM Awal (Sebelum Isi Bensin)')
                                    ->numeric()
                                    ->suffix('KM')
                                    ->placeholder('Contoh: 12540')
                                    ->helperText('Angka odometer sebelum pengisian BBM.'),

                                Forms\Components\TextInput::make('fuel_base_amount')
                                    ->label('Nominal Sesuai Nota (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('Contoh: 200000')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                        $base = (float)$state;
                                        $set('amount', $base);
                                    }),

                                Forms\Components\TextInput::make('fuel_extra_amount')
                                    ->label('Kompensasi Tambahan (+5k Mobil)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(5000)
                                    ->disabled()
                                    ->dehydrated(),
                            ]),

                        Forms\Components\Placeholder::make('pertamax_warning')
                            ->label('')
                            ->content(fn () => new \Illuminate\Support\HtmlString('
                                <div class="p-3 bg-red-50 dark:bg-red-950/50 border border-red-300 dark:border-red-800 rounded-xl flex items-center gap-3 text-xs text-red-700 dark:text-red-300 font-medium">
                                    <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                                    <div><strong>PERINGATAN:</strong> Jenis BBM <u>Pertamax</u> tidak diperbolehkan sesuai SOP Perusahaan. Admin akan otomatis <strong>MENOLAK</strong> pengajuan ini jika tetap diajukan.</div>
                                </div>
                            '))
                            ->visible(fn (Forms\Get $get) => strtoupper((string)$get('fuel_type')) === 'PERTAMAX'),
                    ]),

                // SECTION TRANSAKSI UMUM (JIKA BUKAN PERDIN)
                Forms\Components\Section::make('Rincian Transaksi Klaim Operasional')
                    ->description('Isi rincian pengeluaran: Jenis biaya, Keperluan, Lokasi nota, Brand, Reffnote, dan Nominal')
                    ->visible(fn (Forms\Get $get) => !(bool)$get('is_perdin'))
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Daftar Rincian Pengeluaran')
                            ->schema([
                                Forms\Components\Select::make('claim_type')
                                    ->label('Jenis Biaya')
                                    ->options(function () {
                                        $types = BudgetType::where('status', 'Aktif')->pluck('name', 'name')->toArray();
                                        return !empty($types) ? $types : [
                                            'Entertain' => 'Entertain',
                                            'BBM' => 'BBM',
                                            'Transportasi' => 'Transportasi',
                                            'Perjalanan Dinas' => 'Perjalanan Dinas',
                                            'Service Motor' => 'Service Motor',
                                        ];
                                    })
                                    ->default('Entertain')
                                    ->reactive()
                                    ->required(),

                                Forms\Components\Select::make('entertain_subtype')
                                    ->label('Kategori Entertain')
                                    ->options([
                                        'Makan' => 'Makan (Jamuan Makan / Meeting - Masuk Rekap Plafon)',
                                        'Lainnya' => 'Lainnya (Karangan Bunga & Kue Ultah - Non Plafon)',
                                    ])
                                    ->default('Makan')
                                    ->visible(fn (Forms\Get $get) => $get('claim_type') === 'Entertain')
                                    ->helperText('Hanya sub-kategori Makan yang memotong plafon budget bulanan.'),

                                Forms\Components\TextInput::make('purpose')
                                    ->label('Keperluan')
                                    ->required(fn (Forms\Get $get) => !in_array($get('claim_type'), ['Tol / Parkir', 'Tol', 'Parkir']))
                                    ->placeholder('Contoh: BBM operasional cabang / meetup dealer'),

                                Forms\Components\TextInput::make('note')
                                    ->label('Note (Lokasi / SPBU / Tempat)')
                                    ->required()
                                    ->placeholder('Contoh: SPBU 44.531 / Resto Sumber Rejeki'),

                                Forms\Components\TextInput::make('city')
                                    ->label('Kota')
                                    ->required(fn (Forms\Get $get) => !in_array($get('claim_type'), ['Tol / Parkir', 'Tol', 'Parkir']))
                                    ->placeholder('Contoh: Purwokerto / Cilacap'),

                                Forms\Components\TextInput::make('brand')
                                    ->label('Brand')
                                    ->placeholder('Contoh: REALME / OPPO'),

                                Forms\Components\TextInput::make('reffnote')
                                    ->label('Reffnote')
                                    ->placeholder('Contoh: REALME PURWOKERTO'),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Nominal (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        $items = $get('../../items') ?? [];
                                        $total = 0;
                                        foreach ($items as $it) {
                                            $total += (float)($it['amount'] ?? 0);
                                        }
                                        $set('../../amount', $total);
                                    })
                                    ->placeholder('Contoh: 190000'),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Rincian Transaksi')
                            ->reorderable(false)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Total Nominal Klaim (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->readOnly()
                            ->dehydrated()
                            ->helperText('Jika rincian transaksi di atas diisi, total nominal dihitung otomatis.')
                            ->default(0)
                            ->columnSpan(2),
                    ]),

                // SECTION KHUSUS UPLOAD FOTO BBM (BEFORE & AFTER DIGABUNG)
                Forms\Components\Section::make('Foto BBM Odometer (Sebelum & Sesudah)')
                    ->description('Untuk klaim BBM, upload foto sebelum dan sesudah pada masing-masing form di bawah. Sistem akan otomatis menggabungkannya berdampingan menjadi 1 file foto lampiran resmi.')
                    ->schema([
                        Forms\Components\FileUpload::make('bbm_photo_before')
                            ->label('1. Foto BBM / Odometer Sebelum (Before)')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('claim-photos')
                            ->visibility('public')
                            ->openable()
                            ->downloadable(),

                        Forms\Components\FileUpload::make('bbm_photo_after')
                            ->label('2. Foto BBM / Odometer Sesudah (After)')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('claim-photos')
                            ->visibility('public')
                            ->openable()
                            ->downloadable(),
                    ])->columns(2),

                // SECTION LAMPIRAN BUKTI TRANSAKSI UMUM
                Forms\Components\Section::make('Lampiran Bukti Struk / Nota Lainnya')
                    ->description('Upload foto struk, kwitansi, atau bukti fisik pendukung')
                    ->schema([
                        Forms\Components\FileUpload::make('photos')
                            ->label('Foto Nota / Struk Pembayaran')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->multiple()
                            ->disk('public')
                            ->directory('claim-photos')
                            ->visibility('public')
                            ->reorderable()
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull()
                            ->helperText('Foto yang diunggah akan otomatis dimasukkan ke file Excel dan dokumen pendukung.'),
                    ]),

                // SECTION STATUS APPROVAL & PENCAIRAN
                Forms\Components\Section::make('Status Alur Approval & Pencairan')
                    ->schema([
                        Forms\Components\Select::make('approval_status')
                            ->label('Status Approval')
                            ->options([
                                'DRAFT' => 'DRAFT (Draft Pengajuan)',
                                'DIAJUKAN' => 'DIAJUKAN (Menunggu Acc)',
                                'SEDANG_DIREVISI' => 'SEDANG DIREVISI (Perlu Perbaikan / Revisi)',
                                'ACC_ASM' => 'ACC_ASM (Disetujui ASM - Menunggu Acc RGM)',
                                'ACC_RGM' => 'ACC_RGM (Disetujui RGM - Menunggu Acc Pak Jejen / Selesai)',
                                'ACC_PAK_JEJEN' => 'ACC_PAK_JEJEN (Disetujui Pak Jejen / Management)',
                                'DISETUJUI' => 'DISETUJUI (Selesai Disetujui)',
                                'DITOLAK' => 'DITOLAK (Ditolak)',
                            ])
                            ->default('DRAFT')
                            ->required(),

                        Forms\Components\Select::make('disbursement_status')
                            ->label('Status Pencairan Dana')
                            ->options([
                                'Belum Dicairkan' => 'Belum Dicairkan',
                                'Sudah Dicairkan' => 'Sudah Dicairkan',
                            ])
                            ->default('Belum Dicairkan')
                            ->required(),
                    ])->columns(2),
            ]);
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
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->state(fn (Claim $record) => $record->effective_employee?->name ?? '-')
                    ->description(fn (Claim $record) => $record->effective_employee?->position_name ?? '-'),
                Tables\Columns\TextColumn::make('claim_type_string')
                    ->label('Jenis Biaya')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Entertain' => 'warning',
                        'BBM' => 'success',
                        'Perjalanan Dinas' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('fuel_compliance')
                    ->label('Ketentuan BBM')
                    ->state(fn (Claim $record) => $record->fuel_compliance['label'])
                    ->badge()
                    ->color(fn (Claim $record) => $record->fuel_compliance['color']),
                Tables\Columns\TextColumn::make('purpose_string')
                    ->label('Keperluan')
                    ->searchable()
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->purpose_string),
                Tables\Columns\TextColumn::make('city')
                    ->label('Kota')
                    ->default('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('region')
                    ->label('Region')
                    ->badge()
                    ->color('info')
                    ->state(fn (Claim $record) => $record->region ?: ($record->effective_employee?->region ?: ($record->homebase ?: '-')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal Klaim')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total')),
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
                    ->color(fn (string $state): string => match ($state) {
                        'Sudah Dicairkan' => 'success',
                        'Belum Dicairkan' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('claim_date', 'desc')
            ->filters([
                // 1. Filter Status _UID
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

                // 2. Filter Jenis Budget
                Tables\Filters\SelectFilter::make('claim_type')
                    ->label('Jenis Budget')
                    ->options(function () {
                        $types = BudgetType::where('status', 'Aktif')->pluck('name', 'name')->toArray();
                        return !empty($types) ? $types : [
                            'Entertain' => 'Entertain',
                            'BBM' => 'BBM',
                            'Transportasi' => 'Transportasi',
                            'Perjalanan Dinas' => 'Perjalanan Dinas',
                            'Service Motor' => 'Service Motor',
                        ];
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $val = $data['value'];
                            $query->where(function ($q) use ($val) {
                                $q->where('claim_type', 'like', "%{$val}%")
                                  ->orWhere('items', 'like', "%{$val}%")
                                  ->orWhere(function ($sub) use ($val) {
                                      if ($val === 'Perjalanan Dinas') {
                                          $sub->where('is_perdin', true);
                                      }
                                  });
                            });
                        }
                    }),

                // 2. Filter Jenis BBM
                Tables\Filters\SelectFilter::make('fuel_type')
                    ->label('Jenis Bahan Bakar (BBM)')
                    ->options([
                        'Pertalite' => 'Pertalite',
                        'Pertamax' => 'Pertamax (Tidak Sesuai)',
                        'Solar' => 'Solar / Dexlite',
                    ]),

                // 3. Filter Jenis Kendaraan
                Tables\Filters\SelectFilter::make('vehicle_type')
                    ->label('Jenis Kendaraan')
                    ->options([
                        'Mobil' => 'Mobil',
                        'Motor' => 'Motor',
                    ]),

                // 4. Filter Bulan
                Tables\Filters\SelectFilter::make('month')
                    ->label('Bulan')
                    ->options([
                        '1' => 'Januari',
                        '2' => 'Februari',
                        '3' => 'Maret',
                        '4' => 'April',
                        '5' => 'Mei',
                        '6' => 'Juni',
                        '7' => 'Juli',
                        '8' => 'Agustus',
                        '9' => 'September',
                        '10' => 'Oktober',
                        '11' => 'November',
                        '12' => 'Desember',
                    ])
                    ->query(fn (Builder $query, array $data) => !empty($data['value']) ? $query->whereMonth('claim_date', $data['value']) : $query),

                // 5. Filter Tahun
                Tables\Filters\SelectFilter::make('year')
                    ->label('Tahun')
                    ->options([
                        '2024' => '2024',
                        '2025' => '2025',
                        '2026' => '2026',
                        '2027' => '2027',
                    ])
                    ->query(fn (Builder $query, array $data) => !empty($data['value']) ? $query->whereYear('claim_date', $data['value']) : $query),

                // 6. Filter Status Approval
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

                // 7. Filter Status Pencairan
                Tables\Filters\SelectFilter::make('disbursement_status')
                    ->label('Status Pencairan')
                    ->options([
                        'Belum Dicairkan' => 'Belum Dicairkan',
                        'Sudah Dicairkan' => 'Sudah Dicairkan',
                    ]),

                // 8. Filter Karyawan
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Pemohon')
                    ->relationship('employee', 'name'),

                // 9. Filter Region
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
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_filtered_excel')
                    ->label('Export Rekapan Sesuai Filter')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(function (\Filament\Tables\Contracts\HasTable $livewire) {
                        $claims = $livewire->getFilteredTableQuery()
                            ->with(['employee.role', 'branch'])
                            ->orderBy('claim_date', 'asc')
                            ->get();

                        return app(ClaimExcelExportService::class)->exportClaims($claims, 'REKAPITULASI KLAIM (HASIL FILTER)');
                    }),
            ])
            ->actions([
                // 1. Tombol Lihat Semua Berkas Pengajuan Klaim (Galeri Foto, Struk, Nota, Kwitansi, BA)
                \App\Filament\Actions\ViewClaimFilesAction::make(),

                // 2. Tombol Detail Form Klaim (Read-Only)
                Tables\Actions\ViewAction::make()
                    ->label('Detail')
                    ->color('gray'),

                // 3. Tombol Transfer Dana & Upload Bukti Transfer (Khusus Role Finance)
                Tables\Actions\Action::make('finance_transfer')
                    ->label('Transfer Dana')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn (Claim $record) => auth()->user()?->isFinance() && $record->disbursement_status !== 'Sudah Dicairkan' && $record->approval_status === 'DISETUJUI')
                    ->modalHeading(fn (Claim $record) => "Kirim Bukti Transfer: " . ($record->effective_employee?->name ?? 'Pemohon'))
                    ->modalDescription(fn (Claim $record) => "Nominal yang harus ditransfer: Rp " . number_format((float)$record->amount, 0, ',', '.') . ". Silakan unggah foto struk / bukti transfer bank.")
                    ->form([
                        Forms\Components\FileUpload::make('transfer_proof_photo')
                            ->label('Foto / Bukti Transfer Bank')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('transfer-proofs')
                            ->visibility('public')
                            ->required()
                            ->openable()
                            ->downloadable()
                            ->helperText('Wajib unggah bukti transfer ke pemohon sebagai syarat pencairan dana.'),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $user = auth()->user();
                        $record->update([
                            'transfer_proof_photo' => $data['transfer_proof_photo'],
                            'disbursement_status' => 'Sudah Dicairkan',
                            'disbursed_at' => now(),
                            'finance_approved_by_id' => $user?->id,
                            'finance_approved_at' => now(),
                            'approval_status' => 'DISETUJUI',
                        ]);
                        Notification::make()->title('Dana berhasil dicairkan dan Bukti Transfer tersimpan!')->success()->send();
                    }),

                // 4. Tombol Tolak Pengajuan dengan Catatan (Khusus Role Finance)
                Tables\Actions\Action::make('finance_reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Claim $record) => auth()->user()?->isFinance() && $record->disbursement_status !== 'Sudah Dicairkan' && $record->approval_status !== 'DITOLAK_FINANCE')
                    ->modalHeading(fn (Claim $record) => "Tolak Pengajuan Klaim: " . ($record->effective_employee?->name ?? 'Pemohon'))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Catatan / Alasan Penolakan Finance (Untuk Direvisi Admin)')
                            ->placeholder('Tuliskan alasan mengapa pengajuan klaim ini ditolak...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK_FINANCE',
                            'rejection_reason' => $data['rejection_reason'],
                            'disbursement_status' => 'Belum Dicairkan',
                        ]);
                        Notification::make()->title('Pengajuan klaim berhasil ditolak. Admin dapat merevisi data klaim.')->warning()->send();
                    }),

                // 5. Lihat Bukti Transfer Pembayaran
                \App\Filament\Actions\ViewTransferProofAction::make(),

                // 6. Action: Export Excel Realisasi
                Tables\Actions\Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (Claim $record) => (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin() || auth()->user()?->isFinance()) && $record->claim_category !== 'bbm')
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),

                // Action: Export PDF (BBM / Transport & Entertain)
                Tables\Actions\Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin() || auth()->user()?->isFinance())
                    ->action(fn (Claim $record) => app(\App\Services\ClaimPdfExportService::class)->exportSingleClaimPdf($record)),

                // Action: Export Form Perdin (Khusus Klaim Perdin)
                Tables\Actions\Action::make('export_perdin_excel')
                    ->label('Form Perdin')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->visible(fn (Claim $record) => (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin()) && (bool)$record->is_perdin)
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportPerdinForm($record)),

                // Action: Export Word Form Klaim BBM
                Tables\Actions\Action::make('export_bbm_word')
                    ->label('Word BBM')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (Claim $record) => (str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM') || $record->claim_category === 'bbm') && (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin() || auth()->user()?->isFinance()))
                    ->action(fn (Claim $record) => app(\App\Services\BbmClaimWordExportService::class)->export($record)),

                // Action: Download Berita Acara (Khusus Klaim BBM)
                Tables\Actions\Action::make('download_ba')
                    ->label('Download BA')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->visible(fn (Claim $record) => str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM') || $record->claim_category === 'bbm')
                    ->action(fn (Claim $record) => app(\App\Services\BbmBeritaAcaraWordExportService::class)->export($record)),

                // Action Group untuk Approval Workflow & Pencairan (Khusus Admin / SuperAdmin)
                Tables\Actions\ActionGroup::make([
                    // 0. Set / Edit _UID
                    Tables\Actions\Action::make('set_uid')
                        ->label('Input / Edit _UID')
                        ->icon('heroicon-o-hashtag')
                        ->color('info')
                        ->form([
                            Forms\Components\TextInput::make('_uid')
                                ->label('Nomor _UID dari Form External')
                                ->default(fn (Claim $record) => $record->_uid)
                                ->required(),
                        ])
                        ->action(function (Claim $record, array $data) {
                            $record->update(['_uid' => $data['_uid']]);
                            Notification::make()->title('_UID berhasil disimpan!')->success()->send();
                        }),
                    // 0. Mulai Revisi (Jika Klaim Ditolak)
                    Tables\Actions\Action::make('start_revision')
                        ->label('Mulai Revisi')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->visible(fn (Claim $record) => in_array($record->approval_status, ['DITOLAK', 'DITOLAK_FINANCE']) && (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin() || $record->user_id === auth()->id()))
                        ->action(function (Claim $record) {
                            $record->update(['approval_status' => 'SEDANG_DIREVISI']);
                            Notification::make()->title('Status klaim kini Sedang Direvisi. Silakan perbaiki data lalu klik Ajukan Kembali.')->info()->send();
                        }),

                    // 1. Ajukan Klaim / Ajukan Kembali
                    Tables\Actions\Action::make('submit_claim')
                        ->label(fn (Claim $record) => in_array($record->approval_status, ['DITOLAK', 'SEDANG_DIREVISI']) ? 'Ajukan Kembali' : 'Ajukan (Rekapan)')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn (Claim $record) => in_array($record->approval_status, ['DRAFT', 'DITOLAK', 'SEDANG_DIREVISI']))
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
                            Notification::make()->title('Klaim berhasil diajukan kembali!')->success()->send();
                        }),

                    // 1.1. Verifikasi & Kirim ke Finance (Admin / Super Admin)
                    Tables\Actions\Action::make('admin_verify_approve')
                        ->label('Verifikasi & Kirim ke Finance')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (Claim $record) => $record->approval_status === 'ACC_PAK_JEJEN' && (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin()))
                        ->action(function (Claim $record) {
                            $user = auth()->user();
                            $record->update([
                                'approval_status' => 'DISETUJUI',
                                'admin_approved_by_id' => $user?->id,
                                'admin_approved_at' => now(),
                            ]);
                            Notification::make()->title('Pengajuan klaim telah diverifikasi Admin & diteruskan ke Finance!')->success()->send();
                        }),

                    // 1.2. Kirim Ulang Revisi ke Finance (Setelah Ditolak Finance)
                    Tables\Actions\Action::make('admin_resubmit_to_finance')
                        ->label('Kirim Ulang ke Finance')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (Claim $record) => $record->approval_status === 'DITOLAK_FINANCE' && (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin()))
                        ->action(function (Claim $record) {
                            $user = auth()->user();
                            $record->update([
                                'approval_status' => 'DISETUJUI',
                                'admin_approved_by_id' => $user?->id,
                                'admin_approved_at' => now(),
                            ]);
                            Notification::make()->title('Revisi klaim berhasil dikirimkan kembali ke Finance!')->success()->send();
                        }),

                    // 2. Acc ASM (Alur Sales -> Acc ASM)
                    Tables\Actions\Action::make('acc_asm')
                        ->label('Acc ASM')
                        ->icon('heroicon-o-check')
                        ->color('info')
                        ->visible(fn (Claim $record) => $record->approval_status === 'DIAJUKAN' && Claim::isAsmSupervisorOf(auth()->user(), $record))
                        ->action(function (Claim $record) {
                            $user = auth()->user();
                            $record->update([
                                'approval_status' => 'ACC_ASM',
                                'approved_by_asm_id' => $user?->id,
                                'approved_by_asm_at' => now(),
                            ]);
                            Notification::make()->title('Klaim telah di-ACC oleh ASM!')->info()->send();
                        }),

                    // 3. Acc RGM (Alur ASM -> Acc RGM / Sales -> Acc ASM -> Acc RGM)
                    Tables\Actions\Action::make('acc_rgm')
                        ->label('Acc RGM')
                        ->icon('heroicon-o-check-badge')
                        ->color('primary')
                        ->visible(function (Claim $record) {
                            $user = auth()->user();
                            if (!Claim::isRgmSupervisorOf($user, $record)) return false;
                            $applicantEmp = $record->effective_employee;
                            $isApplicantAsm = ($applicantEmp?->isAsm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'ASM'));
                            return $isApplicantAsm ? ($record->approval_status === 'DIAJUKAN') : ($record->approval_status === 'ACC_ASM');
                        })
                        ->action(function (Claim $record) {
                            $user = auth()->user();
                            $record->update([
                                'approval_status' => 'ACC_RGM',
                                'approved_by_rgm_id' => $user?->id,
                                'approved_by_rgm_at' => now(),
                            ]);
                            Notification::make()->title('Klaim telah di-ACC oleh RGM!')->success()->send();
                        }),

                    // 4. Acc Pak Jejen (Alur RGM -> Acc Pak Jejen)
                    Tables\Actions\Action::make('acc_jejen')
                        ->label('Acc Pak Jejen')
                        ->icon('heroicon-o-star')
                        ->color('success')
                        ->visible(function (Claim $record) {
                            $user = auth()->user();
                            if (!Claim::isJejenApproverOf($user, $record)) return false;
                            $applicantEmp = $record->effective_employee;
                            $isApplicantRgm = ($applicantEmp?->isRgm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'RGM'));
                            return $isApplicantRgm ? ($record->approval_status === 'DIAJUKAN') : ($record->approval_status === 'ACC_RGM');
                        })
                        ->action(function (Claim $record) {
                            $user = auth()->user();
                            $record->update([
                                'approval_status' => 'ACC_PAK_JEJEN',
                                'approved_by_jejen_id' => $user?->id,
                                'approved_by_jejen_at' => now(),
                            ]);
                            Notification::make()->title('Klaim telah di-ACC oleh Pak Jejen!')->success()->send();
                        }),

                    // 5. Tolak Pengajuan
                    Tables\Actions\Action::make('reject_claim')
                        ->label('Tolak Pengajuan')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Alasan Penolakan')
                                ->default(function (Claim $record) {
                                    if (strtoupper($record->fuel_type ?? '') === 'PERTAMAX') {
                                        return 'Ditolak: Jenis BBM Pertamax tidak diperbolehkan (Wajib Pertalite).';
                                    }
                                    if ($record->vehicle_type === 'Mobil' && (float)$record->fuel_extra_amount < 5000) {
                                        return 'Ditolak: Nominal BBM Mobil wajib dilebihkan Rp 5.000.';
                                    }
                                    return '';
                                })
                                ->required(),
                        ])
                        ->action(function (Claim $record, array $data) {
                            $record->update([
                                'approval_status' => 'DITOLAK',
                                'rejection_reason' => $data['rejection_reason'],
                            ]);
                            Notification::make()->title('Pengajuan klaim ditolak.')->warning()->send();
                        }),

                    // 6. Pencairan Dana (Cairkan / Batal Cairkan)
                    Tables\Actions\Action::make('toggle_disbursement')
                        ->label(fn (Claim $record) => $record->disbursement_status === 'Sudah Dicairkan' ? 'Tandai Belum Dicairkan' : 'Tandai Sudah Dicairkan')
                        ->icon('heroicon-o-currency-dollar')
                        ->color(fn (Claim $record) => $record->disbursement_status === 'Sudah Dicairkan' ? 'danger' : 'success')
                        ->action(function (Claim $record) {
                            if ($record->disbursement_status === 'Sudah Dicairkan') {
                                $record->update([
                                    'disbursement_status' => 'Belum Dicairkan',
                                    'disbursed_at' => null,
                                ]);
                                Notification::make()->title('Status diubah ke Belum Dicairkan')->info()->send();
                            } else {
                                $record->update([
                                    'disbursement_status' => 'Sudah Dicairkan',
                                    'disbursed_at' => now(),
                                    'approval_status' => $record->approval_status === 'DRAFT' ? 'DISETUJUI' : $record->approval_status,
                                ]);
                                Notification::make()->title('Dana berhasil ditandai SUDAH DICAIRKAN!')->success()->send();
                            }
                        }),

                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                ->visible(fn () => !auth()->user()?->isFinance()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_selected_excel')
                        ->label('Export Excel Data Terpilih')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                        ->action(fn ($records) => app(ClaimExcelExportService::class)->exportClaims($records)),

                    Tables\Actions\BulkAction::make('bulk_disburse')
                        ->label('Tandai Sudah Dicairkan (Massal)')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn () => !auth()->user()?->isFinance())
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'disbursement_status' => 'Sudah Dicairkan',
                                    'disbursed_at' => now(),
                                ]);
                            }
                            Notification::make()->title('Data terpilih berhasil ditandai Sudah Dicairkan!')->success()->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClaims::route('/'),
            'create' => Pages\CreateClaim::route('/create'),
            'edit' => Pages\EditClaim::route('/{record}/edit'),
        ];
    }
}

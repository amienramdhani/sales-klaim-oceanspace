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

    protected static ?string $modelLabel = 'Nota Balik BBM';

    protected static ?string $pluralModelLabel = 'Nota Balik BBM';

    protected static ?string $navigationLabel = 'Nota Balik BBM';

    public static function getNavigationLabel(): string
    {
        return 'Nota Balik BBM';
    }

    protected static ?int $navigationSort = 1;

    /**
     * ASM, RGM, Admin, SuperAdmin dapat mengakses menu ini (Sales TIDAK BISA KLAIM BBM)
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isExecutiveApprover() || $user->isSales()) return false;
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isAsm() || $user->isRgm();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isFinance() || $user->isSales() || $user->isExecutiveApprover()) return false;
        return true;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isSales() || $user->isExecutiveApprover()) return false;

        // Super Admin & Admin selalu memiliki hak akses penuh untuk mengedit pengajuan (Item 3)
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        // Pengaju biasa tidak bisa edit saat dalam proses approval atau sudah dicairkan
        $inApprovalProcess = in_array($record->approval_status, [
            'ACC_ASM', 'ACC_RGM', 'ACC_ADMIN', 'ACC_PAK_JEJEN',
            'WAITING_YOGA', 'WAITING_NURBAITI', 'WAITING_THIA',
            'DISETUJUI'
        ]) || $record->disbursement_status === 'Sudah Dicairkan';

        if ($inApprovalProcess) {
            return false;
        }

        return in_array($record->approval_status, ['DRAFT', 'DIAJUKAN', 'DITOLAK']);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance()) return false;
        if ($user->isSuperAdmin() || $user->isAdmin()) return true;
        return in_array($record->approval_status, ['DRAFT', 'DITOLAK']);
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance()) return false;
        return $user->isSuperAdmin() || $user->isAdmin();
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
        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')->default(fn () => auth()->id()),
                Forms\Components\Hidden::make('claim_category')->default('bbm'),
                Forms\Components\Hidden::make('claim_type')->default('Nota Balik BBM'),
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
                    ->extraAttributes(['class' => 'bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 rounded-xl p-4']),
                    

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
                            ->live()
                            ->required(),

                        Forms\Components\DatePicker::make('claim_date')
                            ->label('Tanggal Pengajuan')
                            ->default(now())
                            ->closeOnDateSelection()
                            ->required(),

                        Forms\Components\Select::make('branch_id')
                            ->label('Branch / Cabang')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                if ($state) {
                                    $branch = Branch::find($state);
                                    if ($branch) {
                                        if (blank($get('brand')) && !empty($branch->brand)) {
                                            $set('brand', $branch->brand);
                                        }
                                        if (blank($get('reffnote')) && !empty($branch->reffnote)) {
                                            $set('reffnote', $branch->reffnote);
                                        }
                                        if (blank($get('city')) && !empty($branch->city)) {
                                            $set('city', $branch->city);
                                        }
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
                            ->placeholder('Contoh: Purwokerto / Cirebon')
                            ->live(onBlur: true),
                    ])->columns(3),

                // 3. Transaksi BBM (Multi-Transaksi dengan Upload Foto Before & After per Item)
                Forms\Components\Section::make('Daftar Transaksi BBM & Odometer (Bisa Multi-Transaksi)')
                    ->description('Tambahkan 1 atau lebih transaksi pengisian BBM. Setiap transaksi memiliki perhitungan harga/literan serta upload foto odometer sebelum & sesudah.')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Rincian Transaksi Pengisian BBM')
                            ->live()
                            ->schema([
                                Forms\Components\Hidden::make('claim_type')->default('BBM'),

                                Forms\Components\Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])
                                    ->schema([
                                        Forms\Components\DatePicker::make('receipt_date')
                                            ->label('Tanggal Nota BBM')
                                            ->default(now())
                                            ->native(false)
                                            ->closeOnDateSelection()
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

                                Forms\Components\Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])
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
                            ->label('Total Nominal Keseluruhan Nota Balik BBM (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->readOnly()
                            ->dehydrated()
                            ->live()
                            ->helperText('Total otomatis terakumulasi dari seluruh pengisian BBM.')
                            ->default(0)
                            ->columnSpan(2),
                    ]),

                // 4. Form Pengembalian Sisa Dana ke Finance (HANYA MUNCUL JIKA ADA SISA DANA: REALISASI < BUDGET)
                Forms\Components\Section::make('Pengembalian Sisa Dana ke Finance (Transfer Balik)')
                    ->description('Total realisasi nota balik BBM di bawah plafon budget. Silakan transfer balik sisa dana ke rekening Finance dan lampirkan bukti transfer.')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->visible(fn (Forms\Get $get, ?Claim $record) => self::getBbmBudgetInfo($get, $record)['has_remaining'])
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('bbm_remaining_notice')
                            ->hiddenLabel()
                            ->content(function (Forms\Get $get, ?Claim $record) {
                                $info = self::getBbmBudgetInfo($get, $record);
                                $budget = number_format($info['budget'], 0, ',', '.');
                                $amount = number_format($info['amount'], 0, ',', '.');
                                $diff = number_format($info['diff'], 0, ',', '.');
                                return new \Illuminate\Support\HtmlString("
                                    <div class='flex items-start gap-3 p-3.5 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-700 rounded-xl text-emerald-900 dark:text-emerald-100 text-sm'>
                                        <span class='text-2xl'>💰</span>
                                        <div>
                                            <p class='font-bold text-emerald-900 dark:text-emerald-200'>Ada Sisa Dana BBM Sebesar Rp {$diff}</p>
                                            <p class='text-xs text-emerald-700 dark:text-emerald-300 mt-1'>
                                                Plafon Budget Bulanan: <strong>Rp {$budget}</strong> | Realisasi Pengeluaran: <strong>Rp {$amount}</strong>.<br>
                                                Sesuai ketentuan, sisa dana sebesar <strong>Rp {$diff}</strong> wajib ditransfer kembali ke rekening Finance.
                                            </p>
                                        </div>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('return_transfer_amount')
                            ->label('Nominal Ditransfer Balik (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(fn (Forms\Get $get, ?Claim $record) => self::getBbmBudgetInfo($get, $record)['diff'])
                            ->helperText('Nominal sisa dana yang harus ditransfer balik ke Finance.')
                            ->placeholder('Contoh: 250000'),

                        Forms\Components\FileUpload::make('return_transfer_proof')
                            ->label('Foto / Bukti Transfer Balik ke Finance')
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
                            ->placeholder('Contoh: Sisa dana BBM ditransfer ke rek BCA Finance a/n PT Media Selular Indonesia pada 01/09/2026')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                // 5. Peringatan Over Budget BBM - Wajib Bikin Berita Acara (BA) (HANYA MUNCUL JIKA REALISASI > BUDGET)
                Forms\Components\Section::make('Pemberitahuan Over Budget BBM - Wajib Buat Berita Acara (BA)')
                    ->description('Pengeluaran BBM melebihi budget bulanan yang ditetapkan. Wajib membuat Berita Acara (BA) dan mengunggah dokumen/bukti persetujuan.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (Forms\Get $get, ?Claim $record) => self::getBbmBudgetInfo($get, $record)['is_over'])
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('bbm_over_budget_notice')
                            ->hiddenLabel()
                            ->content(function (Forms\Get $get, ?Claim $record) {
                                $info = self::getBbmBudgetInfo($get, $record);
                                $budget = number_format($info['budget'], 0, ',', '.');
                                $amount = number_format($info['amount'], 0, ',', '.');
                                $diff = number_format($info['diff'], 0, ',', '.');
                                $baCreateUrl = \App\Filament\Resources\BbmBeritaAcaraResource::getUrl('create');
                                return new \Illuminate\Support\HtmlString("
                                    <div class='p-4 bg-amber-50 dark:bg-amber-950/40 border-2 border-amber-400 dark:border-amber-600 rounded-xl text-amber-900 dark:text-amber-100'>
                                        <div class='flex items-start gap-3'>
                                            <span class='text-3xl'>⚠️</span>
                                            <div class='flex-1'>
                                                <h4 class='font-bold text-base text-amber-900 dark:text-amber-200'>Perhatian: Pengeluaran BBM Melebihi Budget (Over Budget)!</h4>
                                                <div class='grid grid-cols-1 sm:grid-cols-3 gap-2 my-2 text-xs'>
                                                    <div class='p-2 bg-white dark:bg-slate-800 rounded-lg border border-amber-200 dark:border-amber-700'>
                                                        <span class='text-gray-500'>Plafon Budget BBM:</span><br>
                                                        <strong class='text-sm text-gray-800 dark:text-gray-200'>Rp {$budget}</strong>
                                                    </div>
                                                    <div class='p-2 bg-white dark:bg-slate-800 rounded-lg border border-amber-200 dark:border-amber-700'>
                                                        <span class='text-gray-500'>Total Realisasi BBM:</span><br>
                                                        <strong class='text-sm text-amber-600 dark:text-amber-400'>Rp {$amount}</strong>
                                                    </div>
                                                    <div class='p-2 bg-white dark:bg-slate-800 rounded-lg border border-amber-200 dark:border-amber-700'>
                                                        <span class='text-gray-500'>Kelebihan (Over):</span><br>
                                                        <strong class='text-sm text-red-600 dark:text-red-400'>+Rp {$diff}</strong>
                                                    </div>
                                                </div>
                                                <p class='text-xs text-amber-800 dark:text-amber-300 font-medium'>
                                                    Karena pengeluaran melebihi budget yang telah ditetapkan, Anda <strong>WAJIB membuat Berita Acara (BA)</strong> dan mengunggah dokumen BA / Bukti Persetujuan WA di bawah ini agar klaim dapat diproses.
                                                </p>
                                                <div class='mt-3 flex items-center gap-2'>
                                                    <a href='{$baCreateUrl}' target='_blank' class='inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shadow transition'>
                                                        📝 Buka Form Buat Berita Acara (BA) BBM Baru ↗
                                                    </a>
                                                    <span class='text-[11px] text-gray-500 dark:text-gray-400'>(Buka di tab baru untuk membuat BA)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('ba_wa_proof_photo')
                            ->label('Upload Dokumen Berita Acara (BA) / Bukti Persetujuan WA')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('ba-proofs')
                            ->visibility('public')
                            ->openable()
                            ->downloadable()
                            ->required(fn (Forms\Get $get, ?Claim $record) => self::getBbmBudgetInfo($get, $record)['is_over'])
                            ->helperText('Wajib diupload dokumen BA bertanda tangan atau tangkapan layar (screenshot) persetujuan WA atasan atas kelebihan budget ini.')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('purpose')
                            ->label('Alasan / Penjelasan Over Budget (Keterangan Berita Acara)')
                            ->placeholder('Jelaskan alasan pengeluaran BBM melebihi batas plafon (contoh: mobilitas tinggi luar kota / urgensi operasional)...')
                            ->rows(2)
                            ->required(fn (Forms\Get $get, ?Claim $record) => self::getBbmBudgetInfo($get, $record)['is_over'])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getBbmBudgetInfo(Forms\Get $get, ?Claim $record = null): array
    {
        $empId = $get('employee_id') ?? $record?->employee_id ?? auth()->user()?->getEffectiveEmployeeId();
        $emp = $empId ? Employee::find($empId) : null;
        $budget = $emp ? (float)$emp->bbm_budget : 0.0;
        
        $amount = (float)$get('amount');
        if ($amount <= 0) {
            $items = $get('items') ?? ($record?->items ?? []);
            if (is_array($items)) {
                foreach ($items as $it) {
                    $amount += (float)($it['amount'] ?? 0);
                }
            }
        }
        if ($amount <= 0 && $record) {
            $amount = (float)$record->amount;
        }

        $isOver = ($budget > 0 && $amount > $budget);
        $hasRemaining = ($budget > 0 && $amount > 0 && $amount < $budget);
        $diff = abs($budget - $amount);

        return [
            'budget' => $budget,
            'amount' => $amount,
            'is_over' => $isOver,
            'has_remaining' => $hasRemaining,
            'diff' => $diff,
            'emp' => $emp,
        ];
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
                Tables\Columns\TextColumn::make('_uid')
                    ->label('_UID')
                    ->badge()
                    ->color('info')
                    ->weight('bold')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('claim_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
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
                Tables\Actions\ActionGroup::make([
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
                    ->modalDescription('Apakah Anda menyetujui pengajuan nota balik BBM ini untuk diteruskan ke RGM?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_ASM',
                            'approved_by_asm_id' => $user?->id,
                            'approved_by_asm_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan nota balik BBM disetujui ASM! Diteruskan ke RGM.')->info()->send();
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
                        Notification::make()->title('Pengajuan nota balik BBM ditolak ASM.')->warning()->send();
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
                        $isApplicantAsm = ($applicantEmp?->isAsm() || $record->user?->isAsm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'ASM'));
                        return $isApplicantAsm ? in_array($record->approval_status, ['ACC_ASM', 'DIAJUKAN']) : ($record->approval_status === 'ACC_ASM');
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Klaim (RGM)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan nota balik BBM ini untuk diteruskan ke Admin untuk verifikasi?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_RGM',
                            'approved_by_rgm_id' => $user?->id,
                            'approved_by_rgm_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan nota balik BBM disetujui RGM! Diteruskan ke Admin untuk verifikasi.')->primary()->send();
                    }),

                Tables\Actions\Action::make('reject_rgm')
                    ->label('Tolak RGM')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!Claim::isRgmSupervisorOf($user, $record)) return false;
                        $applicantEmp = $record->effective_employee;
                        $isApplicantAsm = ($applicantEmp?->isAsm() || $record->user?->isAsm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'ASM'));
                        return $isApplicantAsm ? in_array($record->approval_status, ['ACC_ASM', 'DIAJUKAN']) : ($record->approval_status === 'ACC_ASM');
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
                        Notification::make()->title('Pengajuan nota balik BBM ditolak RGM.')->warning()->send();
                    }),

                // 3. Admin Verifikasi & Setujui -> Kirim ke Head of Sales (Pak Jejen)
                Tables\Actions\Action::make('admin_verify_approve')
                    ->label('Verifikasi & Kirim ke Pak Jejen')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) return false;
                        return in_array($record->approval_status, ['ACC_RGM', 'DITOLAK_FINANCE']) ||
                               ($record->approval_status === 'DIAJUKAN' && ($record->effective_employee?->isRgm() || $record->user?->isRgm()));
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi & Teruskan ke Head of Sales')
                    ->modalDescription('Pastikan seluruh bukti BBM dan kelengkapan berkas telah sah. Klaim akan diteruskan ke Head of Sales (Pak Jejen) untuk persetujuan.')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_ADMIN',
                            'admin_approved_by_id' => $user?->id,
                            'admin_approved_at' => now(),
                        ]);
                        Notification::make()->title('Nota Balik BBM telah diverifikasi Admin! Diteruskan ke Head of Sales (Pak Jejen).')->success()->send();
                    }),

                // 3.1 Admin Tolak
                Tables\Actions\Action::make('admin_reject')
                    ->label('Tolak Admin')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) return false;
                        return in_array($record->approval_status, ['ACC_RGM', 'DITOLAK_FINANCE']) ||
                               ($record->approval_status === 'DIAJUKAN' && ($record->effective_employee?->isRgm() || $record->user?->isRgm()));
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
                        Notification::make()->title('Pengajuan nota balik BBM ditolak oleh Admin.')->warning()->send();
                    }),

                // 4. Approval Atasan: ACC Pak Jejen (Head of Sales) -> Teruskan sesuai Badan Usaha
                Tables\Actions\Action::make('acc_jejen')
                    ->label('ACC Pak Jejen')
                    ->icon('heroicon-o-star')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!Claim::isJejenApproverOf($user, $record)) return false;
                        return $record->approval_status === 'ACC_ADMIN';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Nota Balik BBM (Head of Sales)')
                    ->modalDescription(function (Claim $record) {
                        $isTop = $record->isTopSelular();
                        $nextTarget = $isTop ? 'Manajer Keuangan (Thia Aisyah) - CV Top Selular' : 'Manager Finance (Yoga P. Hadi) - PT Media Selular Indonesia';
                        return "Apakah Anda menyetujui pengajuan nota balik BBM ini? Pengajuan akan diteruskan ke {$nextTarget}.";
                    })
                    ->form([
                        Forms\Components\Textarea::make('approval_note')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->placeholder('Tambahkan catatan atau arahan persetujuan jika ada...')
                            ->rows(2),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $user = auth()->user();
                        $isTop = $record->isTopSelular();

                        if ($isTop) {
                            $nextStatus = 'WAITING_THIA';
                            $msg = 'Pengajuan nota balik BBM disetujui Pak Jejen! Diteruskan ke Bu Thia Aisyah (Manajer Keuangan CV Top Selular).';
                        } else {
                            $nextStatus = 'WAITING_YOGA';
                            $msg = 'Pengajuan nota balik BBM disetujui Pak Jejen! Diteruskan ke Pak Yoga P. Hadi (Manager Finance PT Media Selular Indonesia).';
                        }

                        $record->update([
                            'approval_status' => $nextStatus,
                            'approved_by_jejen_id' => $user?->id,
                            'approved_by_jejen_at' => now(),
                            'jejen_approval_note' => $data['approval_note'] ?? null,
                            'approval_note' => $data['approval_note'] ?? $record->approval_note,
                        ]);
                        Notification::make()->title($msg)->success()->send();
                    }),

                Tables\Actions\Action::make('reject_jejen')
                    ->label('Tolak Pak Jejen')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!Claim::isJejenApproverOf($user, $record)) return false;
                        return $record->approval_status === 'ACC_ADMIN';
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
                        Notification::make()->title('Pengajuan nota balik BBM ditolak Pak Jejen.')->warning()->send();
                    }),

                // 4.1.A Approval Manager Finance (Pak Yoga P. Hadi - PT MSI)
                Tables\Actions\Action::make('approve_yoga')
                    ->label('ACC Pak Yoga')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_YOGA' && ($user->isYoga() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Manager Finance (Pak Yoga P. Hadi)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan nota balik BBM ini untuk diteruskan ke Finance Coordinator (Bu Nurbaeti Riaseha)?')
                    ->form([
                        Forms\Components\Textarea::make('approval_note')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->placeholder('Tambahkan catatan atau instruksi jika ada...')
                            ->rows(2),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'WAITING_NURBAITI',
                            'approved_by_yoga_id' => $user?->id,
                            'approved_by_yoga_at' => now(),
                            'yoga_approval_note' => $data['approval_note'] ?? null,
                            'approval_note' => $data['approval_note'] ?? $record->approval_note,
                        ]);
                        Notification::make()->title('Nota Balik BBM disetujui Pak Yoga! Diteruskan ke Bu Nurbaeti Riaseha (Finance Coordinator).')->success()->send();
                    }),

                Tables\Actions\Action::make('reject_yoga')
                    ->label('Tolak Pak Yoga')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_YOGA' && ($user->isYoga() || $user->isSuperAdmin());
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan Pak Yoga')
                            ->placeholder('Tuliskan rincian yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Nota Balik BBM ditolak Pak Yoga.')->warning()->send();
                    }),

                // 4.1.B Approval Finance Coordinator (Bu Nurbaeti Riaseha - PT MSI)
                Tables\Actions\Action::make('approve_nurbaiti')
                    ->label('ACC Bu Nurbaeti')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_NURBAITI' && ($user->isNurbaiti() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Finance Coordinator (Bu Nurbaeti Riaseha)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan nota balik BBM ini untuk diteruskan ke antrean pencairan Finance PT Media Selular Indonesia?')
                    ->form([
                        Forms\Components\Textarea::make('approval_note')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->placeholder('Tambahkan catatan atau instruksi jika ada...')
                            ->rows(2),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'approved_by_nurbaiti_id' => $user?->id,
                            'approved_by_nurbaiti_at' => now(),
                            'nurbaiti_approval_note' => $data['approval_note'] ?? null,
                            'approval_note' => $data['approval_note'] ?? $record->approval_note,
                        ]);
                        Notification::make()->title('Nota Balik BBM disetujui Bu Nurbaeti! Diteruskan ke antrean pencairan Finance PT Media Selular Indonesia.')->success()->send();
                    }),

                Tables\Actions\Action::make('reject_nurbaiti')
                    ->label('Tolak Bu Nurbaeti')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_NURBAITI' && ($user->isNurbaiti() || $user->isSuperAdmin());
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan Bu Nurbaeti')
                            ->placeholder('Tuliskan rincian yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Nota Balik BBM ditolak Bu Nurbaeti.')->warning()->send();
                    }),

                // 4.2 Approval Manajer Keuangan (Bu Thia Aisyah - CV Top Selular)
                Tables\Actions\Action::make('approve_thia')
                    ->label('ACC Bu Thia')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_THIA' && ($user->isThia() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Manajer Keuangan (Thia Aisyah)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan nota balik BBM ini untuk diteruskan ke antrean pencairan Finance CV Top Selular?')
                    ->form([
                        Forms\Components\Textarea::make('approval_note')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->placeholder('Tambahkan catatan atau instruksi jika ada...')
                            ->rows(2),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'approved_by_thia_id' => $user?->id,
                            'approved_by_thia_at' => now(),
                            'thia_approval_note' => $data['approval_note'] ?? null,
                            'approval_note' => $data['approval_note'] ?? $record->approval_note,
                        ]);
                        Notification::make()->title('Nota Balik BBM disetujui Bu Thia! Diteruskan ke antrean pencairan Finance CV Top Selular.')->success()->send();
                    }),

                Tables\Actions\Action::make('reject_thia')
                    ->label('Tolak Bu Thia')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_THIA' && ($user->isThia() || $user->isSuperAdmin());
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan Bu Thia')
                            ->placeholder('Tuliskan rincian yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Nota Balik BBM ditolak Bu Thia.')->warning()->send();
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
                    ->modalDescription('Apakah data revisi nota balik BBM sudah benar dan siap diajukan kembali ke Finance untuk pencairan?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'admin_approved_by_id' => $user?->id,
                            'admin_approved_at' => now(),
                        ]);
                        Notification::make()->title('Nota Balik BBM hasil revisi berhasil dikirimkan kembali ke Finance!')->success()->send();
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
                    ->modalHeading('Ajukan Kembali Pengajuan Nota Balik BBM')
                    ->modalDescription('Pastikan foto odometer dan struk BBM telah diperbaiki sesuai catatan sebelum diajukan kembali.')
                    ->action(function (Claim $record) {
                        $isAsm = $record->effective_employee?->isAsm() || $record->user?->isAsm();
                        $status = $isAsm ? 'ACC_ASM' : 'DIAJUKAN';
                        $record->update([
                            'approval_status' => $status,
                            'approved_by_asm_id' => $isAsm ? ($record->user_id ?? auth()->id()) : null,
                            'approved_by_asm_at' => $isAsm ? now() : null,
                            'approved_by_rgm_id' => null,
                            'approved_by_rgm_at' => null,
                            'approved_by_jejen_id' => null,
                            'approved_by_jejen_at' => null,
                            'admin_approved_by_id' => null,
                            'admin_approved_at' => null,
                        ]);
                        Notification::make()->title('Nota Balik BBM berhasil diajukan kembali ke atasan!')->success()->send();
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
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin()),
                ]),
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

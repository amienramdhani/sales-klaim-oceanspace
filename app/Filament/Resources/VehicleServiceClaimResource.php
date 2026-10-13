<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleServiceClaimResource\Pages;
use App\Models\Branch;
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

class VehicleServiceClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'PENGAJUAN KLAIM';

    protected static ?string $navigationLabel = 'Klaim Service Motor';

    protected static ?string $modelLabel = 'Klaim Service Motor';

    protected static ?string $pluralModelLabel = 'Klaim Service Motor';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isExecutiveApprover()) return false;
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isAsm() || $user->isRgm() || $user->isSales();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isFinance() || $user->isExecutiveApprover()) return false;
        return true;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isExecutiveApprover()) return false;

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

        $isOwner = ($record->user_id === $user->id || $record->employee_id === $user->getEffectiveEmployeeId());
        return $isOwner && in_array($record->approval_status, ['DRAFT', 'DIAJUKAN', 'DITOLAK']);
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance()) return false;
        return true;
    }

    public static function recalculateServiceTotal(Forms\Get $get, Forms\Set $set): void
    {
        $itemsFromParent = $get('../../items');
        $isInsideItem = ($itemsFromParent !== null);

        $items = $isInsideItem ? $itemsFromParent : ($get('items') ?? []);
        $total = 0;
        $names = [];

        if (is_array($items)) {
            foreach ($items as $item) {
                $rawAmt = $item['amount'] ?? 0;
                $amt = is_string($rawAmt) ? (float)preg_replace('/[^0-9]/', '', $rawAmt) : (float)$rawAmt;
                $total += $amt;
                $name = $item['item_name'] ?? ($item['name'] ?? null);
                if (!empty($name)) {
                    $names[] = $name;
                }
            }
        }

        $summary = !empty($names) ? implode(', ', array_unique($names)) : 'Service Rutin Sepeda Motor';

        if ($isInsideItem) {
            $set('../../amount', $total);
            $set('../../purpose', $summary);
        } else {
            $set('amount', $total);
            $set('purpose', $summary);
        }
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()
            ->where(function ($q) {
                $q->where('claim_category', 'service')
                  ->orWhere('claim_category', 'service_motor')
                  ->orWhere('claim_type', 'like', '%Service%');
            })
            ->with(['employee.role', 'employee.positionModel', 'branch', 'claimPeriod.employee']);

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
                Forms\Components\Hidden::make('claim_category')->default('service'),

                // SECTION STATUS & _UID (Hanya untuk Admin / Finance / Super Admin)
                Forms\Components\Section::make('Status & _UID Finance')
                    ->icon('heroicon-o-identification')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin() || auth()->user()?->isFinance())
                    ->schema([
                        Forms\Components\TextInput::make('_uid')
                            ->label('_UID (ID Form External)')
                            ->placeholder('Diisi setelah admin input ke form external'),

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
                            ->required(),

                        Forms\Components\Select::make('disbursement_status')
                            ->label('Status Pencairan')
                            ->options([
                                'Belum Dicairkan' => 'Belum Dicairkan',
                                'Sudah Dicairkan' => 'Sudah Dicairkan',
                            ])
                            ->default('Belum Dicairkan')
                            ->required(),
                    ])->columns(3)
                    ->extraAttributes(['class' => 'bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 rounded-xl p-4']),

                Forms\Components\Section::make('Informasi Pemohon Service Motor')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Pemohon / Sales')
                            ->relationship('employee', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->name} ({$record->position_name})")
                            ->default(fn () => auth()->user()?->getEffectiveEmployeeId())
                            ->disabled(fn () => !auth()->user()?->isAdmin() && !auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                if ($state) {
                                    $emp = Employee::find($state);
                                    if ($emp) {
                                        $branches = $emp->getManagedBranchesList();
                                        $bId = !empty($branches) ? $branches[0] : null;
                                        if (blank($get('branch_id')) && $bId) {
                                            $set('branch_id', $bId);
                                            $br = Branch::find($bId);
                                            if ($br && blank($get('reffnote'))) {
                                                $set('reffnote', $br->reffnote);
                                            }
                                        }
                                    }
                                }
                            })
                            ->required(),

                        Forms\Components\DatePicker::make('claim_date')
                            ->label('Tanggal Service')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('branch_id')
                            ->label('Branch / Cabang')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                if ($state) {
                                    $branch = Branch::find($state);
                                    if ($branch) {
                                        if (blank($get('reffnote')) && !empty($branch->reffnote)) {
                                            $set('reffnote', $branch->reffnote);
                                        }
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('reffnote')
                            ->label('Reffnote')
                            ->placeholder('Otomatis dari cabang / Ketik manual')
                            ->dehydrated(),
                    ])->columns([
                        'default' => 1,
                        'sm' => 2,
                        'md' => 4,
                    ]),

                Forms\Components\Section::make('Rincian Klaim Service Motor Sales')
                    ->description('Pilih item pengerjaan & suku cadang service motor operasional. Anda dapat memilih, menambah, atau menghapus item yang tidak dilakukan.')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('vehicle_type')
                                    ->label('Jenis Kendaraan')
                                    ->options([
                                        'Motor' => 'Motor Pribadi / Operasional',
                                        'Mobil' => 'Mobil Pribadi / Operasional',
                                    ])
                                    ->default('Motor')
                                    ->required(),

                                Forms\Components\TextInput::make('note')
                                    ->label('Nama Bengkel / Tempat Service')
                                    ->required()
                                    ->placeholder('Contoh: AHASS Cirebon / Bengkel Resmi Yamaha'),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Total Biaya Service (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->dehydrated()
                                    ->readOnly()
                                    ->extraAttributes(['class' => 'font-bold text-sky-700 dark:text-sky-300 text-lg'])
                                    ->helperText('Otomatis dihitung dari akumulasi item di bawah'),
                            ]),

                        Forms\Components\Repeater::make('items')
                            ->label('Daftar Pilihan Item Servis (Pilih & Hapus Item Sesuai Kebutuhan)')
                            ->addActionLabel('+ Tambah Item Servis')
                            ->live()
                            ->defaultItems(2)
                            ->default([
                                ['item_name' => 'Ganti Oli Mesin', 'amount' => 0, 'purpose' => 'Ganti oli mesin motor operasional'],
                                ['item_name' => 'Servis Berkala / Tune Up', 'amount' => 0, 'purpose' => 'Pembersihan & pengecekan berkala'],
                            ])
                            ->schema([
                                Forms\Components\Grid::make([
                                    'default' => 1,
                                    'md' => 12,
                                ])->schema([
                                    Forms\Components\Select::make('item_name')
                                        ->label('Pilih Item Servis')
                                        ->options([
                                            'Ganti Oli Mesin' => 'Ganti Oli Mesin',
                                            'Ganti Oli Gardan / Transmisi' => 'Ganti Oli Gardan / Transmisi',
                                            'Servis Berkala / Tune Up' => 'Servis Berkala / Tune Up',
                                            'Ganti Kampas Rem' => 'Ganti Kampas Rem (Depan / Belakang)',
                                            'Ganti Filter Udara' => 'Ganti Filter Udara',
                                            'Ganti Busi' => 'Ganti Busi',
                                            'Ganti Ban (Luar / Dalam)' => 'Ganti Ban (Luar / Dalam)',
                                            'Ganti Aki / Baterai' => 'Ganti Aki / Baterai',
                                            'Ganti V-Belt / Rantai / Roller' => 'Ganti V-Belt / Rantai / Roller',
                                            'Perbaikan Lampu / Kelistrikan' => 'Perbaikan Lampu / Kelistrikan',
                                            'Jasa Pengerjaan Bengkel' => 'Jasa Pengerjaan Bengkel',
                                            'Item Lainnya' => 'Item Lainnya (Ketik Manual)',
                                        ])
                                        ->searchable()
                                        ->createOptionForm([
                                            Forms\Components\TextInput::make('name')
                                                ->label('Nama Item Servis Custom')
                                                ->required(),
                                        ])
                                        ->createOptionUsing(fn ($data) => $data['name'])
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                            if (empty($get('purpose')) && !empty($state)) {
                                                $set('purpose', $state);
                                            }
                                            self::recalculateServiceTotal($get, $set);
                                        })
                                        ->columnSpan([
                                            'default' => 12,
                                            'md' => 5,
                                        ]),

                                    Forms\Components\TextInput::make('purpose')
                                        ->label('Keterangan / Merk Sparepart')
                                        ->placeholder('Contoh: MPX1 / Yamalube / Kampas Asli')
                                        ->columnSpan([
                                            'default' => 12,
                                            'md' => 4,
                                        ]),

                                    Forms\Components\TextInput::make('amount')
                                        ->label('Biaya (Rp)')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0)
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                            self::recalculateServiceTotal($get, $set);
                                        })
                                        ->columnSpan([
                                            'default' => 12,
                                            'md' => 3,
                                        ]),
                                ]),
                            ])
                            ->deletable(true)
                            ->deleteAction(fn (Forms\Components\Actions\Action $action) => $action->requiresConfirmation(false)->after(function (Forms\Get $get, Forms\Set $set) {
                                self::recalculateServiceTotal($get, $set);
                            }))
                            ->addAction(fn (Forms\Components\Actions\Action $action) => $action->after(function (Forms\Get $get, Forms\Set $set) {
                                self::recalculateServiceTotal($get, $set);
                            }))
                            ->reorderAction(fn (Forms\Components\Actions\Action $action) => $action->after(function (Forms\Get $get, Forms\Set $set) {
                                self::recalculateServiceTotal($get, $set);
                            }))
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                self::recalculateServiceTotal($get, $set);
                            })
                            ->reorderable(true)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('purpose')
                            ->label('Ringkasan Pengerjaan Servis')
                            ->helperText('Otomatis terisi dari daftar item servis yang dipilih di atas')
                            ->columnSpanFull()
                            ->readOnly()
                            ->dehydrated(),
                    ]),

                Forms\Components\Section::make('Lampiran Wajib Foto Before & After Service Motor')
                    ->description('Wajib melampirkan foto motor/odometer SEBELUM dan SESUDAH service. Sistem akan menggabungkan kedua foto secara berdampingan tanpa mengurangi resolusi dan kualitas.')
                    ->icon('heroicon-o-camera')
                    ->schema([
                        Forms\Components\FileUpload::make('service_photo_before')
                            ->label('Foto Sebelum Service (Before)')
                            ->helperText('Foto fisik motor atau speedometer odometer sebelum masuk pengerjaan bengkel')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('claim-service-photos')
                            ->visibility('public')
                            ->openable()
                            ->downloadable()
                            ->required(),

                        Forms\Components\FileUpload::make('service_photo_after')
                            ->label('Foto Sesudah Service (After)')
                            ->helperText('Foto fisik motor atau speedometer odometer sesudah selesai pengerjaan bengkel')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('claim-service-photos')
                            ->visibility('public')
                            ->openable()
                            ->downloadable()
                            ->required(),

                        Forms\Components\FileUpload::make('service_photo_combined')
                            ->label('Hasil Foto Before & After (Otomatis Digabung Sistem)')
                            ->helperText('Foto kombinasi berdampingan kualitas tinggi (High Definition) yang dibuat otomatis oleh sistem.')
                            ->image()
                            ->disk('public')
                            ->directory('claim-service-photos')
                            ->visibility('public')
                            ->openable()
                            ->downloadable()
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record?->service_photo_combined)),
                    ])->columns(2),

                Forms\Components\Section::make('Lampiran Bukti Nota / Kwitansi Bengkel')
                    ->icon('heroicon-o-receipt-percent')
                    ->schema([
                        Forms\Components\FileUpload::make('photos')
                            ->label('Foto Kwitansi & Rincian Sparepart Bengkel')
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
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('claim_date')
                    ->label('Tanggal Service')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('_uid')
                    ->label('_UID')
                    ->badge()
                    ->color('info')
                    ->weight('bold')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
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
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('vehicle_type')
                    ->label('Kendaraan')
                    ->badge()
                    ->color('info')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('note')
                    ->label('Bengkel')
                    ->limit(25)
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal Biaya')
                    ->money('IDR')
                    ->weight('bold')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total')),
                Tables\Columns\ImageColumn::make('service_photo_combined')
                    ->label('Foto Before/After')
                    ->disk('public')
                    ->height(40)
                    ->placeholder('-')
                    ->visibleFrom('md'),
                Tables\Columns\ImageColumn::make('transfer_proof_photo')
                    ->label('Bukti Transfer Finance')
                    ->disk('public')
                    ->height(40)
                    ->placeholder('-')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Status Approval & Tracking')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Claim $record): string => $record->approval_stage_label)
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'DIAJUKAN' => 'warning',
                        'ACC_ASM' => 'info',
                        'ACC_RGM', 'ACC_ADMIN' => 'primary',
                        'ACC_PAK_JEJEN', 'WAITING_YOGA', 'WAITING_NURBAITI', 'WAITING_THIA' => 'indigo',
                        'DISETUJUI' => 'success',
                        'SEDANG_DIREVISI' => 'warning',
                        'DITOLAK_FINANCE', 'DITOLAK' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (Claim $record) => $record->approval_journey_summary),
                Tables\Columns\TextColumn::make('disbursement_status')
                    ->label('Pencairan')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sudah Dicairkan' ? 'success' : 'danger')
                    ->visibleFrom('md'),
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

                Tables\Filters\SelectFilter::make('vehicle_type')
                    ->label('Kendaraan')
                    ->options([
                        'Motor' => 'Motor',
                        'Mobil' => 'Mobil',
                    ]),

                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Status Approval')
                    ->options([
                        'DRAFT' => 'DRAFT',
                        'DIAJUKAN' => 'DIAJUKAN',
                        'ACC_ASM' => 'ACC_ASM',
                        'ACC_RGM' => 'ACC_RGM',
                        'ACC_PAK_JEJEN' => 'ACC_PAK_JEJEN',
                        'DISETUJUI' => 'DISETUJUI',
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
                Tables\Actions\Action::make('export_service_excel')
                    ->label('Export Rekapan Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(function (\Filament\Tables\Contracts\HasTable $livewire) {
                        $claims = $livewire->getFilteredTableQuery()
                            ->with(['employee.role', 'branch'])
                            ->orderBy('claim_date', 'asc')
                            ->get();

                        return app(ClaimExcelExportService::class)->exportClaims($claims, 'REKAPITULASI KLAIM SERVICE MOTOR SALES');
                    }),
            ])
            ->actions([
                \App\Filament\Actions\ViewTransferProofAction::make(),

                Tables\Actions\Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn (Claim $record) => app(\App\Services\ClaimPdfExportService::class)->exportVehicleServiceSinglePdf($record)),

                Tables\Actions\Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),

                // 1. Approval Atasan: ACC ASM
                Tables\Actions\Action::make('acc_asm')
                    ->label('ACC ASM')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!Claim::isAsmSupervisorOf($user, $record)) return false;
                        return $record->approval_status === 'DIAJUKAN';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Klaim (ASM)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim service motor ini untuk diteruskan ke RGM?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_ASM',
                            'approved_by_asm_id' => $user?->id,
                            'approved_by_asm_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan klaim disetujui ASM! Diteruskan ke RGM.')->success()->send();
                    }),

                // 2. Approval Atasan: ACC RGM
                Tables\Actions\Action::make('acc_rgm')
                    ->label('ACC RGM')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!Claim::isRgmSupervisorOf($user, $record)) return false;
                        return in_array($record->approval_status, ['ACC_ASM', 'DIAJUKAN']);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Klaim (RGM)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim service motor ini untuk diteruskan ke Admin?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_RGM',
                            'approved_by_rgm_id' => $user?->id,
                            'approved_by_rgm_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan klaim disetujui RGM! Diteruskan ke Admin untuk verifikasi.')->success()->send();
                    }),

                // 3. Verifikasi Admin
                Tables\Actions\Action::make('acc_admin')
                    ->label('ACC Admin')
                    ->icon('heroicon-o-shield-check')
                    ->color('primary')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) return false;
                        return in_array($record->approval_status, ['ACC_RGM', 'DITOLAK_FINANCE']) || ($record->approval_status === 'DIAJUKAN' && ($record->effective_employee?->isRgm() || $record->user?->isRgm()));
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Verifikasi Admin')
                    ->modalDescription('Klaim akan diverifikasi dan diteruskan ke Head of Sales (Pak Jejen).')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_ADMIN',
                            'admin_approved_by_id' => $user?->id,
                            'admin_approved_at' => now(),
                        ]);
                        Notification::make()->title('Klaim berhasil diverifikasi Admin dan diteruskan ke Head of Sales (Pak Jejen)!')->success()->send();
                    }),

                // 4. ACC Head of Sales (Pak Jejen) -> Teruskan sesuai Badan Usaha
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
                    ->modalHeading('Konfirmasi Persetujuan Klaim (Head of Sales)')
                    ->modalDescription(function (Claim $record) {
                        $isTop = $record->isTopSelular();
                        $nextTarget = $isTop ? 'Manajer Keuangan (Thia Aisyah) - CV Top Selular' : 'Manager Finance (Yoga P. Hadi) - PT Media Selular Indonesia';
                        return "Apakah Anda menyetujui pengajuan klaim service motor ini? Pengajuan akan diteruskan ke {$nextTarget}.";
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
                            $msg = 'Pengajuan klaim disetujui Pak Jejen! Diteruskan ke Bu Thia Aisyah (Manajer Keuangan CV Top Selular).';
                        } else {
                            $nextStatus = 'WAITING_YOGA';
                            $msg = 'Pengajuan klaim disetujui Pak Jejen! Diteruskan ke Pak Yoga P. Hadi (Manager Finance PT Media Selular Indonesia).';
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
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan klaim ditolak Pak Jejen.')->warning()->send();
                    }),

                // 4.1.A ACC Manager Finance (Pak Yoga P. Hadi - PT MSI)
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
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim service motor ini untuk diteruskan ke Finance Coordinator (Bu Nurbaeti Riaseha)?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'WAITING_NURBAITI',
                            'approved_by_yoga_id' => $user?->id,
                            'approved_by_yoga_at' => now(),
                        ]);
                        Notification::make()->title('Klaim disetujui Pak Yoga! Diteruskan ke Bu Nurbaeti Riaseha (Finance Coordinator).')->success()->send();
                    }),

                // 4.1.B ACC Finance Coordinator (Bu Nurbaeti Riaseha - PT MSI)
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
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim service motor ini untuk diteruskan ke antrean pencairan Finance PT Media Selular Indonesia?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'approved_by_nurbaiti_id' => $user?->id,
                            'approved_by_nurbaiti_at' => now(),
                        ]);
                        Notification::make()->title('Klaim disetujui Bu Nurbaeti! Diteruskan ke antrean pencairan Finance PT Media Selular Indonesia.')->success()->send();
                    }),

                // 4.1.C ACC Manajer Keuangan (Thia Aisyah - CV Top Selular)
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
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim service motor ini untuk diteruskan ke antrean pencairan Finance CV Top Selular?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'approved_by_thia_id' => $user?->id,
                            'approved_by_thia_at' => now(),
                        ]);
                        Notification::make()->title('Klaim disetujui Bu Thia! Diteruskan ke antrean pencairan Finance CV Top Selular.')->success()->send();
                    }),

                // Ajukan Kembali
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
                        Notification::make()->title('Klaim berhasil diajukan kembali ke atasan!')->success()->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicleServiceClaims::route('/'),
            'create' => Pages\CreateVehicleServiceClaim::route('/create'),
            'edit' => Pages\EditVehicleServiceClaim::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerdinClaimResource\Pages;
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
use Illuminate\Database\Eloquent\Model;

class PerdinClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'PENGAJUAN KLAIM';

    protected static ?string $navigationLabel = 'Klaim Perjalanan Dinas (Perdin)';

    protected static ?string $modelLabel = 'Klaim Perjalanan Dinas';

    protected static ?string $pluralModelLabel = 'Klaim Perjalanan Dinas (Perdin)';

    protected static ?int $navigationSort = 3;

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

    public static function canEdit(Model $record): bool
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

        // Pengaju biasa dapat mengedit saat DRAFT, DIAJUKAN, DITOLAK
        return in_array($record->approval_status, ['DRAFT', 'DIAJUKAN', 'DITOLAK']);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isExecutiveApprover()) return false;
        if ($user->isSuperAdmin() || $user->isAdmin()) return true;
        return in_array($record->approval_status, ['DRAFT', 'DITOLAK']);
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isExecutiveApprover()) return false;
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    /**
     * Cek apakah user adalah ASM atasan langsung dari pemohon klaim
     */
    public static function isAsmSupervisorOf(?\App\Models\User $user, Claim $record): bool
    {
        return Claim::isAsmSupervisorOf($user, $record);
    }

    /**
     * Cek apakah user adalah RGM atasan dari pemohon (atasan dari ASM atau atasan langsung ASM)
     */
    public static function isRgmSupervisorOf(?\App\Models\User $user, Claim $record): bool
    {
        if (!$user) return false;
        if ($user->isSuperAdmin() || $user->isAdmin()) return true;
        if (!$user->isRgm()) return false;

        $applicantEmp = $record->effective_employee;
        if (!$applicantEmp) return false;

        $rgmEmpId = $user->getEffectiveEmployeeId();
        // 1. Jika pemohon adalah ASM langsung di bawah RGM ini
        if ($rgmEmpId && $applicantEmp->supervisor_id == $rgmEmpId) {
            return true;
        }

        // 2. Jika pemohon adalah Sales yang ASM-nya berada di bawah RGM ini
        if ($rgmEmpId && $applicantEmp->supervisor?->supervisor_id == $rgmEmpId) {
            return true;
        }

        // Fallback: region
        $userRegions = $user->getRegionList();
        if ($applicantEmp->region && in_array(strtoupper($applicantEmp->region), $userRegions)) {
            return true;
        }

        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where(function ($q) {
                $q->where('is_perdin', true)
                  ->orWhere('claim_category', 'perdin');
            })
            ->with(['employee.role', 'employee.positionModel', 'employee.supervisor', 'branch', 'claimPeriod.employee']);

        $user = auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isYoga()) {
            return $query->where(function ($q) {
                $q->where('approval_status', 'WAITING_YOGA')
                  ->orWhereNotNull('approved_by_yoga_at');
            });
        }

        if ($user->isNurbaiti()) {
            return $query->where(function ($q) {
                $q->where('approval_status', 'WAITING_NURBAITI')
                  ->orWhereNotNull('approved_by_nurbaiti_at');
            });
        }

        if ($user->isThia()) {
            return $query->where(function ($q) {
                $q->where('approval_status', 'WAITING_THIA')
                  ->orWhereNotNull('approved_by_thia_at');
            });
        }

        if ($user->isJejen()) {
            return $query->whereIn('approval_status', [
                'ACC_RGM', 'ACC_ADMIN', 'DIAJUKAN', 'WAITING_YOGA', 'WAITING_NURBAITI', 'WAITING_THIA', 'DISETUJUI', 'DITOLAK'
            ]);
        }

        if ($user->isFinance()) {
            $query->where(function ($sq) {
                $sq->whereIn('approval_status', ['DISETUJUI', 'DITOLAK_FINANCE'])
                   ->orWhere('disbursement_status', 'Sudah Dicairkan');
            });

            if (!empty($user->badan_usaha)) {
                $bu = $user->badan_usaha;
                $query->where(function ($sub) use ($bu) {
                    $sub->where('badan_usaha', $bu)->orWhere('company', $bu);
                });
            }

            return $query;
        }

        if ($user->isAdmin()) {
            return $query->visibleToUser($user);
        }

        if ($user->isFieldUser() && !$user->isAdmin() && !$user->isSuperAdmin()) {
            return $query->ownSubmissionsOnly($user);
        }

        return $query->visibleToUser($user);
    }

    public static function getEffectiveEmployeeRecord(Forms\Get $get): ?Employee
    {
        $empId = $get('employee_id') ?: auth()->user()?->getEffectiveEmployeeId();
        if ($empId) {
            return Employee::with(['role', 'positionModel'])->find($empId);
        }
        return null;
    }

    public static function isSalesUser(Forms\Get $get): bool
    {
        $emp = static::getEffectiveEmployeeRecord($get);
        if ($emp) {
            $role = strtoupper($emp->role?->code ?? $emp->role?->name ?? $emp->position ?? '');
            return str_contains($role, 'SALES') || str_contains($role, 'SLS') || str_contains($role, 'DSF');
        }
        return auth()->user()?->isSales() ?? false;
    }

    public static function isAsmUser(Forms\Get $get): bool
    {
        $emp = static::getEffectiveEmployeeRecord($get);
        if ($emp) {
            $role = strtoupper($emp->role?->code ?? $emp->role?->name ?? $emp->position ?? '');
            return str_contains($role, 'ASM');
        }
        return auth()->user()?->isAsm() ?? false;
    }

    public static function getPerdinMealRateByRole(Forms\Get $get): float
    {
        $emp = static::getEffectiveEmployeeRecord($get);
        if ($emp) {
            $role = strtoupper($emp->role?->code ?? $emp->role?->name ?? $emp->position ?? '');
            if (str_contains($role, 'RGM')) return 100000.0;
            if (str_contains($role, 'ASM')) return 75000.0;
            if (str_contains($role, 'SUPERVISOR') || str_contains($role, 'SPV') || str_contains($role, 'ASC')) return 50000.0;
            if (str_contains($role, 'SALES') || str_contains($role, 'SLS') || str_contains($role, 'DSF')) return 35000.0;
        }

        $user = auth()->user();
        if ($user) {
            if ($user->isRgm()) return 100000.0;
            if ($user->isAsm()) return 75000.0;
            if ($user->isSales()) return 35000.0;
        }

        return 35000.0;
    }

    /**
     * Otomatisasi perhitungan dana perdin dari lama hari dan rincian biaya
     */
    public static function recalculatePerdin(Forms\Get $get, Forms\Set $set, ?int $daysOverride = null): void
    {
        // 1. Tentukan lama hari (minimal 1 hari)
        if ($daysOverride !== null) {
            $days = max(1, $daysOverride);
        } else {
            $depDate = $get('claim_date');
            $returnDate = $get('perdin_return_date');
            if ($depDate && $returnDate) {
                $diff = \Carbon\Carbon::parse($depDate)->diffInDays(\Carbon\Carbon::parse($returnDate)) + 1;
                $days = max(1, $diff);
            } else {
                $days = max(1, (int)($get('days_count') ?? 1));
            }
        }

        $set('days_count', $days);
        $set('nights_count', 0);

        // 2. Uang makan di-lock berdasarkan role (Sales 35k, SPV 50k, ASM 75k, RGM 100k)
        $mealRate = static::getPerdinMealRateByRole($get);
        $meal = (float)($days * $mealRate);
        $set('meal_allowance_qty', $days);
        $set('meal_allowance_rate', $mealRate);
        $set('meal_allowance', $meal);
        $set('lodging_allowance', 0.0);

        // 3. Transportasi BBM (Sales default Rp 50.000, tapi bisa dihapus/diubah ke 0 jika pakai travel; ASM form ditiadakan / 0)
        $isSales = static::isSalesUser($get);
        $isAsm = static::isAsmUser($get);

        if ($isAsm) {
            $fuel = 0.0;
            $set('fuel_cost', 0.0);
        } else {
            $rawFuel = $get('fuel_cost');
            if ($rawFuel === null || $rawFuel === '') {
                $fuel = $isSales ? 50000.0 : 0.0;
                $set('fuel_cost', $fuel);
            } else {
                $fuel = (float)$rawFuel;
                if ($isSales && $fuel > 50000.0) {
                    $fuel = 50000.0;
                    $set('fuel_cost', $fuel);
                }
            }
        }

        // 4. Transportasi Travel
        $travel = (float)($get('travel_cost') ?? 0);

        // 5. Biaya Sewa Motor
        $rental = (float)($get('car_rental_cost') ?? 0);

        // 6. Transportasi (Lainnya)
        $other = (float)($get('other_transport_cost') ?? ($get('toll_cost') ?? 0));
        $set('toll_cost', $other);

        $total = $meal + $fuel + $travel + $rental + $other;
        $set('amount', $total);
    }

    public static function recalculateNotaBalik(Forms\Get $get, Forms\Set $set, Claim $record): void
    {
        $actualMeal = (float)($get('actual_meal_allowance') ?? $record->actual_meal_allowance ?? $record->meal_allowance);
        $actualFuel = (float)($get('actual_fuel_cost') ?? $record->actual_fuel_cost ?? $record->fuel_cost);
        $actualTravel = (float)($get('actual_travel_cost') ?? $record->actual_travel_cost ?? $record->travel_cost);
        $actualRental = (float)($get('actual_car_rental_cost') ?? $record->actual_car_rental_cost ?? $record->car_rental_cost);
        $actualOther = (float)($get('actual_other_transport_cost') ?? $record->actual_other_transport_cost ?? $record->actual_toll_cost ?? $record->other_transport_cost ?? $record->toll_cost);

        $actualTotal = $actualMeal + $actualFuel + $actualTravel + $actualRental + $actualOther;
        $remaining = max(0, ((float)$record->amount) - $actualTotal);

        $set('actual_amount_total', $actualTotal);
        $set('remaining_amount_calculated', $remaining);
        $set('return_transfer_amount', $remaining);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->disabled(fn (?Claim $record) => $record && !static::canEdit($record))
            ->schema([
                Forms\Components\Hidden::make('user_id')->default(fn () => auth()->id()),
                Forms\Components\Hidden::make('claim_category')->default('perdin'),
                Forms\Components\Hidden::make('is_perdin')->default(true),
                Forms\Components\Hidden::make('nights_count')->default(0),
                Forms\Components\Hidden::make('lodging_allowance')->default(0),
                Forms\Components\Hidden::make('homebase')->default('Purwokerto'),

                // TOP SECTION: _UID EXTERNAL & STATUS PENGAJUAN (Hanya untuk Admin / Finance / Super Admin)
                Forms\Components\Section::make('_UID EXTERNAL & STATUS PENGAJUAN')
                    ->description('Nomor referensi ID Form External dan status approval sistem. Terekam di awal formulir.')
                    ->icon('heroicon-o-identification')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin() || auth()->user()?->isFinance())
                    ->schema([
                        Forms\Components\TextInput::make('_uid')
                            ->label('_UID (ID Form External)')
                            ->placeholder('Contoh: MSI-12345 atau UID-12345')
                            ->helperText('Setelah _UID terisi, pengajuan otomatis masuk ke antrean Finance.')
                            ->columnSpan(1),

                        Forms\Components\Select::make('approval_status')
                            ->label('Status Approval')
                            ->options([
                                'DRAFT' => 'DRAFT',
                                'DIAJUKAN' => 'DIAJUKAN',
                                'ACC_ASM' => 'ACC_ASM',
                                'ACC_RGM' => 'ACC_RGM',
                                'ACC_ADMIN' => 'ACC_ADMIN',
                                'ACC_PAK_JEJEN' => 'ACC_PAK_JEJEN',
                                'WAITING_YOGA' => 'Menunggu ACC Pak Yoga (Manager Finance)',
                                'WAITING_NURBAITI' => 'Menunggu ACC Bu Nurbaiti (Finance Coordinator)',
                                'WAITING_THIA' => 'Menunggu ACC Bu Thia Aisyah (Manajer Keuangan)',
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

                Forms\Components\Section::make('Informasi Pemohon & Tujuan Perjalanan Dinas')
                    ->description('Pilih badan usaha, karyawan pemohon, dan kota tujuan dinas')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Select::make('badan_usaha')
                            ->label('Badan Usaha')
                            ->options([
                                'PT MEDIA SELULAR INDONESIA' => 'PT MEDIA SELULAR INDONESIA',
                                'CV TOP SELULAR' => 'CV TOP SELULAR',
                            ])
                            ->default(fn () => auth()->user()?->badan_usaha ?? 'PT MEDIA SELULAR INDONESIA')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('company', $state)),

                        Forms\Components\Select::make('employee_id')
                            ->label('Pemohon / Karyawan (Sales / ASM / RGM)')
                            ->relationship('employee', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->name} ({$record->position_name})")
                            ->default(fn () => auth()->user()?->getEffectiveEmployeeId())
                            ->disabled(fn () => !auth()->user()?->isAdmin() && !auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                self::recalculatePerdin($get, $set);
                            })
                            ->required(),

                        Forms\Components\TextInput::make('destination_city')
                            ->label('Kota Tujuan')
                            ->required()
                            ->placeholder('Contoh: Semarang / Solo / Cirebon')
                            ->helperText('Tuliskan kota tujuan dinas'),
                    ])->columns(3),

                Forms\Components\Section::make('Informasi Rekening Bank Pencairan')
                    ->description('Pencairan dana akan ditransfer ke rekening di bawah ini. Anda dapat memasukkan rekening lain jika diperlukan.')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Forms\Components\Placeholder::make('default_bank_info')
                            ->label('Rekening Default Karyawan')
                            ->content(function (Forms\Get $get) {
                                $emp = static::getEffectiveEmployeeRecord($get);
                                if (!$emp || (empty($emp->bank_name) && empty($emp->bank_account_number))) {
                                    return 'Belum ada rekening default terdaftar di profil karyawan.';
                                }
                                return "Bank {$emp->bank_name} - {$emp->bank_account_number} a/n {$emp->bank_account_name}";
                            })
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('use_custom_bank')
                            ->label('Gunakan Rekening Lain untuk Pengajuan Ini?')
                            ->default(fn (?Claim $record) => !empty($record?->custom_bank_name) || !empty($record?->custom_bank_account_number))
                            ->reactive()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('custom_bank_name')
                            ->label('Nama Bank Alternatif')
                            ->placeholder('Contoh: BCA / MANDIRI / BRI / BNI')
                            ->datalist(['BCA', 'MANDIRI', 'BRI', 'BNI', 'BSI', 'CIMB NIAGA', 'PERMATA', 'DANAMON', 'JAGO', 'SEABANK'])
                            ->visible(fn (Forms\Get $get) => (bool)$get('use_custom_bank'))
                            ->required(fn (Forms\Get $get) => (bool)$get('use_custom_bank')),

                        Forms\Components\TextInput::make('custom_bank_account_number')
                            ->label('Nomor Rekening Alternatif')
                            ->placeholder('Contoh: 1234567890')
                            ->visible(fn (Forms\Get $get) => (bool)$get('use_custom_bank'))
                            ->required(fn (Forms\Get $get) => (bool)$get('use_custom_bank')),

                        Forms\Components\TextInput::make('custom_bank_account_name')
                            ->label('Nama Pemilik Rekening Alternatif')
                            ->placeholder('Contoh: NAMA DI BUKU TABUNGAN')
                            ->visible(fn (Forms\Get $get) => (bool)$get('use_custom_bank'))
                            ->required(fn (Forms\Get $get) => (bool)$get('use_custom_bank')),
                    ])->columns(3),

                Forms\Components\Section::make('Jadwal Perjalanan Dinas & Agenda')
                    ->description('Tentukan tanggal keberangkatan dan kepulangan. Jumlah hari otomatis mengalikan uang makan harian.')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        Forms\Components\DatePicker::make('claim_date')
                            ->label('Tanggal Berangkat')
                            ->default(now())
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                $days = max(1, (int)($get('days_count') ?? 1));
                                if ($state) {
                                    $set('perdin_return_date', \Carbon\Carbon::parse($state)->addDays($days - 1)->format('Y-m-d'));
                                }
                                self::recalculatePerdin($get, $set, $days);
                            })
                            ->required(),

                        Forms\Components\DatePicker::make('perdin_return_date')
                            ->label('Tanggal Pulang / Selesai')
                            ->default(now()->addDays(1))
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                $depDate = $get('claim_date');
                                if ($depDate && $state) {
                                    $diff = \Carbon\Carbon::parse($depDate)->diffInDays(\Carbon\Carbon::parse($state)) + 1;
                                    self::recalculatePerdin($get, $set, max(1, $diff));
                                } else {
                                    self::recalculatePerdin($get, $set);
                                }
                            })
                            ->required()
                            ->helperText('Batas akhir Nota Balik: Maksimal H+1 setelah tanggal pulang.'),

                        Forms\Components\TextInput::make('days_count')
                            ->label('Jumlah Hari Dinas')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                $days = max(1, (int)$state);
                                $depDate = $get('claim_date');
                                if ($depDate) {
                                    $set('perdin_return_date', \Carbon\Carbon::parse($depDate)->addDays($days - 1)->format('Y-m-d'));
                                }
                                self::recalculatePerdin($get, $set, $days);
                            })
                            ->dehydrateStateUsing(fn ($state) => max(1, (int)($state ?? 1)))
                            ->helperText('Otomatis menghitung Uang Makan')
                            ->required(),

                        Forms\Components\TextInput::make('distance_km')
                            ->label('Jarak Perjalanan (KM)')
                            ->numeric()
                            ->suffix('KM')
                            ->default(85)
                            ->required()
                            ->helperText('Ketentuan: Minimal jarak 80 km')
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    if ($value < 80) {
                                        $fail('Minimal jarak perjalanan dinas adalah 80 km.');
                                    }
                                };
                            }),

                        Forms\Components\TextInput::make('purpose')
                            ->label('Keperluan / Agenda Perjalanan Dinas')
                            ->default('VISIT DEALER & OPERASIONAL CABANG')
                            ->columnSpanFull()
                            ->required(),
                    ])->columns(4),

                Forms\Components\Section::make('Rincian Estimasi Biaya Perjalanan Dinas')
                    ->description('Uang makan terkunci otomatis berdasarkan role (Sales 35k, SPV 50k, ASM 75k, RGM 100k/hari). Transportasi terdiri dari BBM, Travel, Sewa Motor, dan Lainnya.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Fieldset::make('1. Tunjangan Uang Makan')
                                    ->schema([
                                        Forms\Components\TextInput::make('meal_allowance_qty')
                                            ->label('Qty (Hari)')
                                            ->numeric()
                                            ->default(fn (Forms\Get $get) => max(1, (int)($get('days_count') ?? 1)))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                $days = max(1, (int)($state ?? 1));
                                                $set('days_count', $days);
                                                self::recalculatePerdin($get, $set, $days);
                                            })
                                            ->helperText('Jumlah hari perdin'),

                                        Forms\Components\TextInput::make('meal_allowance_rate')
                                            ->label('Jumlah / Tarif (Rp)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default(fn (Forms\Get $get) => static::getPerdinMealRateByRole($get))
                                            ->helperText(function (Forms\Get $get) {
                                                $rate = static::getPerdinMealRateByRole($get);
                                                return 'Terkunci: Rp ' . number_format($rate, 0, ',', '.') . ' / hari';
                                            }),

                                        Forms\Components\TextInput::make('meal_allowance')
                                            ->label('Total Nominal (Qty x Jumlah)')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->disabled()
                                            ->dehydrated()
                                            ->default(function (Forms\Get $get) {
                                                $rate = static::getPerdinMealRateByRole($get);
                                                $days = max(1, (int)($get('days_count') ?? 1));
                                                return (float)($days * $rate);
                                            })
                                            ->helperText('Otomatis: Qty x Tarif'),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('fuel_cost')
                                    ->label(fn (Forms\Get $get) => static::isSalesUser($get) ? '2. Transportasi (BBM) - Jatah Sales' : '2. Transportasi (BBM)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(fn (Forms\Get $get) => static::isSalesUser($get) ? 50000.0 : 0.0)
                                    ->dehydrated()
                                    ->visible(fn (Forms\Get $get) => !static::isAsmUser($get))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                        $isSales = static::isSalesUser($get);
                                        if ($isSales && (float)$state > 50000.0) {
                                            $set('fuel_cost', 50000.0);
                                        }
                                        self::recalculatePerdin($get, $set);
                                    })
                                    ->helperText(fn (Forms\Get $get) => static::isSalesUser($get) ? 'Jatah BBM Sales Rp 50.000 (Dapat dihapus / diubah ke 0 jika menggunakan travel)' : 'Biaya bensin / BBM kendaraan'),

                                Forms\Components\TextInput::make('travel_cost')
                                    ->label('3. Transportasi (Travel)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalculatePerdin($get, $set))
                                    ->dehydrateStateUsing(fn ($state) => (float)($state ?? 0))
                                    ->helperText('Biaya tiket / armada travel antar kota'),

                                Forms\Components\TextInput::make('car_rental_cost')
                                    ->label('4. Biaya Sewa Motor')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalculatePerdin($get, $set))
                                    ->dehydrateStateUsing(fn ($state) => (float)($state ?? 0))
                                    ->helperText('Biaya sewa sepeda motor operasional'),

                                Forms\Components\TextInput::make('other_transport_cost')
                                    ->label('5. Transportasi (Lainnya)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                        $set('toll_cost', (float)($state ?? 0));
                                        self::recalculatePerdin($get, $set);
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (float)($state ?? 0))
                                    ->helperText('Biaya tol / karcis parkir / penyeberangan / lainnya'),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Total Estimasi Dana Perdin (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(function (Forms\Get $get) {
                                        $meal = static::getPerdinMealRateByRole($get) * max(1, (int)($get('days_count') ?? 1));
                                        $fuel = static::isSalesUser($get) ? 50000.0 : 0.0;
                                        return $meal + $fuel;
                                    })
                                    ->helperText('Otomatis dihitung dari Uang Makan + Transportasi'),
                            ]),
                    ]),

                Forms\Components\Section::make('Lampiran Bukti Tiket / Pendukung Operasional')
                    ->description('Lampirkan foto tiket transportasi atau dokumen pendukung perdin jika ada (struk makan tidak diperlukan, kwitansi uang makan dibuat otomatis sistem).')
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('photos')
                            ->label('Foto Tiket / Dokumen Pendukung (Opsional)')
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
                Tables\Columns\TextColumn::make('_uid')
                    ->label('_UID')
                    ->badge()
                    ->color('info')
                    ->weight('bold')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('kwitansi_number')
                    ->label('No. Kwitansi')
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('claim_date')
                    ->label('Tgl Berangkat')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('perdin_return_date')
                    ->label('Tgl Pulang')
                    ->date('d/m/Y')
                    ->placeholder('-')
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

                Tables\Columns\TextColumn::make('destination_city')
                    ->label('Kota Tujuan')
                    ->description(fn (Claim $record) => "{$record->distance_km} KM ({$record->days_count} Hari)"),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Total Biaya')
                    ->money('IDR')
                    ->weight('bold')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total')),

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

                Tables\Columns\TextColumn::make('admin_gform_submitted')
                    ->label('Google Form Atasan')
                    ->state(fn (Claim $record) => $record->admin_gform_submitted ? 'Sudah Diajukan' : 'Belum')
                    ->badge()
                    ->color(fn (Claim $record) => $record->admin_gform_submitted ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('disbursement_status')
                    ->label('Pencairan Finance')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sudah Dicairkan' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('nota_balik_status')
                    ->label('Status Nota Balik')
                    ->state(function (Claim $record) {
                        if ($record->nota_balik_finance_sent) return 'Rekap Terkirim Finance';
                        if ($record->nota_balik_submitted) return 'Nota Balik Masuk Admin';
                        if ($record->disbursement_status === 'Sudah Dicairkan') {
                            $deadline = $record->perdin_return_deadline;
                            if ($deadline && now()->gt($deadline->endOfDay())) {
                                return 'Terlambat Nota Balik';
                            }
                            return 'Menunggu Nota Balik';
                        }
                        return '-';
                    })
                    ->badge()
                    ->color(function (Claim $record) {
                        if ($record->nota_balik_finance_sent) return 'success';
                        if ($record->nota_balik_submitted) return 'info';
                        if ($record->disbursement_status === 'Sudah Dicairkan') {
                            $deadline = $record->perdin_return_deadline;
                            if ($deadline && now()->gt($deadline->endOfDay())) {
                                return 'danger';
                            }
                            return 'warning';
                        }
                        return 'gray';
                    }),
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

                Tables\Filters\SelectFilter::make('badan_usaha')
                    ->label('Badan Usaha')
                    ->options([
                        'PT MEDIA SELULAR INDONESIA' => 'PT MEDIA SELULAR INDONESIA',
                        'CV COMPLETE SELULAR' => 'CV COMPLETE SELULAR',
                    ]),

                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Status Approval')
                    ->options([
                        'DIAJUKAN' => 'DIAJUKAN (Menunggu Approval)',
                        'ACC_ASM' => 'ACC_ASM (Disetujui ASM)',
                        'ACC_RGM' => 'ACC_RGM (Disetujui RGM)',
                        'ACC_ADMIN' => 'ACC_ADMIN (Disetujui Admin)',
                        'ACC_PAK_JEJEN' => 'ACC_PAK_JEJEN (Disetujui Pak Jejen)',
                        'WAITING_YOGA' => 'Menunggu ACC Pak Yoga (Manager Finance)',
                        'WAITING_NURBAITI' => 'Menunggu ACC Bu Nurbaiti (Finance Coordinator)',
                        'WAITING_THIA' => 'Menunggu ACC Bu Thia Aisyah (Manajer Keuangan)',
                        'DISETUJUI' => 'DISETUJUI (Siap Dicairkan Finance)',
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
                Tables\Actions\Action::make('export_perdin_excel')
                    ->label('Export Rekapan Perdin')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(function (\Filament\Tables\Contracts\HasTable $livewire) {
                        $claims = $livewire->getFilteredTableQuery()
                            ->with(['employee.role', 'branch'])
                            ->orderBy('claim_date', 'asc')
                            ->get();

                        return app(ClaimExcelExportService::class)->exportPerdinRecapExcel($claims);
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                // 1. Approval Action: ACC ASM (Izinkan / Tolak oleh ASM Atasan)
                Tables\Actions\Action::make('acc_asm')
                    ->label('Izinkan (ACC ASM)')
                    ->icon('heroicon-o-check')
                    ->color('info')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'DIAJUKAN' && static::isAsmSupervisorOf($user, $record);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Izin Perjalanan Dinas (ASM)')
                    ->modalDescription('Apakah Anda mengizinkan pengajuan perjalanan dinas ini untuk diteruskan ke RGM?')
                    ->form([
                        Forms\Components\Textarea::make('approval_note')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->placeholder('Tambahkan catatan atau instruksi jika ada...')
                            ->rows(2),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_ASM',
                            'approved_by_asm_id' => $user?->id,
                            'approved_by_asm_at' => now(),
                            'asm_approval_note' => $data['approval_note'] ?? null,
                            'approval_note' => $data['approval_note'] ?? $record->approval_note,
                        ]);
                        Notification::make()->title('Pengajuan Perdin diizinkan oleh ASM! Menunggu persetujuan RGM.')->info()->send();
                    }),

                Tables\Actions\Action::make('reject_asm')
                    ->label('Tolak / Revisi ASM')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'DIAJUKAN' && static::isAsmSupervisorOf($user, $record);
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan / Catatan Perbaikan')
                            ->placeholder('Tuliskan rincian yang perlu diperbaiki oleh Sales...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan Perdin ditolak oleh ASM untuk diperbaiki pemohon.')->warning()->send();
                    }),

                // 2. Approval Action: ACC RGM (Izinkan / Tolak oleh RGM Atasan)
                Tables\Actions\Action::make('acc_rgm')
                    ->label('Izinkan (ACC RGM)')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        $applicantEmp = $record->effective_employee;
                        $isApplicantAsm = ($applicantEmp?->isAsm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'ASM'));
                        $validStatus = $isApplicantAsm ? ($record->approval_status === 'DIAJUKAN') : ($record->approval_status === 'ACC_ASM');
                        return $validStatus && static::isRgmSupervisorOf($user, $record);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Izin Perjalanan Dinas (RGM)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan perjalanan dinas ini?')
                    ->form([
                        Forms\Components\Textarea::make('approval_note')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->placeholder('Tambahkan catatan atau instruksi jika ada...')
                            ->rows(2),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_RGM',
                            'approved_by_rgm_id' => $user?->id,
                            'approved_by_rgm_at' => now(),
                            'rgm_approval_note' => $data['approval_note'] ?? null,
                            'approval_note' => $data['approval_note'] ?? $record->approval_note,
                        ]);
                        Notification::make()->title('Pengajuan Perdin di-ACC RGM! Diteruskan ke Admin untuk verifikasi.')->success()->send();
                    }),

                Tables\Actions\Action::make('reject_rgm')
                    ->label('Tolak / Revisi RGM')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        $applicantEmp = $record->effective_employee;
                        $isApplicantAsm = ($applicantEmp?->isAsm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'ASM'));
                        $validStatus = $isApplicantAsm ? ($record->approval_status === 'DIAJUKAN') : ($record->approval_status === 'ACC_ASM');
                        return $validStatus && static::isRgmSupervisorOf($user, $record);
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan / Catatan Perbaikan')
                            ->placeholder('Tuliskan rincian yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan Perdin ditolak oleh RGM untuk diperbaiki pemohon.')->warning()->send();
                    }),

                // 2.1 Aksi Ajukan Kembali (Pemohon setelah perbaikan data)
                Tables\Actions\Action::make('resubmit_perdin')
                    ->label('Ajukan Kembali')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        $isApplicant = ($user->getEffectiveEmployeeId() == $record->employee_id || $record->user_id == $user->id);
                        return in_array($record->approval_status, ['DITOLAK', 'SEDANG_DIREVISI']) && ($isApplicant || $user->isAdmin() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Ajukan Kembali Pengajuan Perjalanan Dinas')
                    ->modalDescription('Pastikan data dan dokumen pengajuan telah diperbaiki sesuai catatan atasan sebelum diajukan kembali.')
                    ->action(function (Claim $record) {
                        $record->update([
                            'approval_status' => 'DIAJUKAN',
                            'approved_by_asm_id' => null,
                            'approved_by_asm_at' => null,
                            'approved_by_rgm_id' => null,
                            'approved_by_rgm_at' => null,
                        ]);
                        Notification::make()->title('Pengajuan Perdin berhasil diajukan kembali ke atasan!')->success()->send();
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
                    ->modalDescription('Pastikan data form perdin dan persetujuan atasan telah sah. Pengajuan akan diteruskan ke Head of Sales (Pak Jejen) untuk persetujuan.')
                    ->form([
                        Forms\Components\Textarea::make('approval_note')
                            ->label('Catatan Verifikasi Admin (Opsional)')
                            ->placeholder('Tambahkan catatan verifikasi jika ada...')
                            ->rows(2),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_ADMIN',
                            'admin_approved_by_id' => $user?->id,
                            'admin_approved_at' => now(),
                            'admin_approval_note' => $data['approval_note'] ?? null,
                            'approval_note' => $data['approval_note'] ?? $record->approval_note,
                        ]);
                        Notification::make()->title('Pengajuan Perdin diverifikasi Admin! Diteruskan ke Head of Sales (Pak Jejen).')->success()->send();
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
                        Notification::make()->title('Pengajuan Perdin ditolak oleh Admin.')->warning()->send();
                    }),

                // 3.2 Admin Action: Ajukan ke Google Form (Pak Jejen & Pak Yoga)
                Tables\Actions\Action::make('submit_to_gform')
                    ->label('Ajukan ke G-Form Atasan')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return !$record->admin_gform_submitted && 
                               ($user->isAdmin() || $user->isSuperAdmin()) &&
                               in_array($record->approval_status, ['ACC_RGM', 'ACC_ADMIN', 'DISETUJUI', 'DIAJUKAN']);
                    })
                    ->form([
                        Forms\Components\TextInput::make('admin_gform_url')
                            ->label('Link Google Form Manajemen')
                            ->default('https://docs.google.com/forms/d/e/perdin-management/viewform')
                            ->helperText('Pengajuan Perdin akan diteruskan ke Pak Jejen dan Pak Yoga melalui form ini.')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'admin_gform_submitted' => true,
                            'admin_gform_submitted_at' => now(),
                            'admin_gform_url' => $data['admin_gform_url'],
                        ]);
                        Notification::make()->title('Pengajuan Perdin berhasil diteruskan ke Google Form Management!')->success()->send();
                    }),

                // 4. Approval Action: ACC Pak Jejen (Head of Sales) -> Teruskan sesuai Badan Usaha (Item 17)
                Tables\Actions\Action::make('acc_jejen')
                    ->label('ACC Pak Jejen')
                    ->icon('heroicon-o-star')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return in_array($record->approval_status, ['ACC_ADMIN', 'ACC_RGM', 'DIAJUKAN']) && ($user->isJejen() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Perdin (Head of Sales - Pak Jejen)')
                    ->modalDescription(function (Claim $record) {
                        $bu = strtoupper($record->badan_usaha ?? '');
                        $isTopSelular = str_contains($bu, 'TOP') || str_contains($bu, 'COMPLETE') || str_contains($bu, 'CV');
                        $nextRecipient = $isTopSelular ? 'Bu Thia Aisyah (Manajer Keuangan CV Top Selular)' : 'Pak Yoga P. Hadi (Manager Finance PT MSI)';
                        return "Apakah Anda menyetujui pengajuan perjalanan dinas ini? Pengajuan akan diteruskan ke {$nextRecipient}.";
                    })
                    ->form([
                        Forms\Components\Textarea::make('approval_note')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->placeholder('Tambahkan arahan atau catatan persetujuan jika ada...')
                            ->rows(2),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $user = auth()->user();
                        $bu = strtoupper($record->badan_usaha ?? '');
                        $isTopSelular = str_contains($bu, 'TOP') || str_contains($bu, 'COMPLETE') || str_contains($bu, 'CV');

                        if ($isTopSelular) {
                            $nextStatus = 'WAITING_THIA';
                            $msg = 'Pengajuan Perdin disetujui Pak Jejen! Diteruskan ke Bu Thia Aisyah (Manajer Keuangan CV Top Selular).';
                        } else {
                            $nextStatus = 'WAITING_YOGA';
                            $msg = 'Pengajuan Perdin disetujui Pak Jejen! Diteruskan ke Pak Yoga P. Hadi (Manager Finance PT MSI).';
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
                        if (!$user) return false;
                        return in_array($record->approval_status, ['ACC_ADMIN', 'ACC_RGM', 'DIAJUKAN']) && ($user->isJejen() || $user->isSuperAdmin());
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan Pak Jejen')
                            ->placeholder('Tuliskan rincian yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan Perdin ditolak Pak Jejen.')->warning()->send();
                    }),

                // 4.1.A Approval Pak Yoga P. Hadi (Manager Finance PT MSI)
                Tables\Actions\Action::make('approve_yoga')
                    ->label('ACC Pak Yoga (Manager Finance)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_YOGA' && ($user->isYoga() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Manager Finance (Pak Yoga P. Hadi)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan perjalanan dinas ini untuk diteruskan ke Koordinator Finance (Bu Nurbaiti Riaseha)?')
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
                        Notification::make()->title('Pengajuan Perdin disetujui Pak Yoga! Diteruskan ke Bu Nurbaiti Riaseha (Koordinator Finance).')->success()->send();
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
                        Notification::make()->title('Pengajuan Perdin ditolak Pak Yoga.')->warning()->send();
                    }),

                // 4.1.B Approval Bu Nurbaiti Riaseha (Koordinator Finance PT MSI)
                Tables\Actions\Action::make('approve_nurbaiti')
                    ->label('ACC Bu Nurbaiti (Koordinator Finance)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_NURBAITI' && ($user->isNurbaiti() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Koordinator Finance (Bu Nurbaiti Riaseha)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan perjalanan dinas ini untuk diteruskan ke Finance Media Selular Indonesia untuk proses pencairan dana?')
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
                        Notification::make()->title('Pengajuan Perdin disetujui Bu Nurbaiti! Diteruskan ke antrean pencairan Finance Media Selular Indonesia.')->success()->send();
                    }),

                Tables\Actions\Action::make('reject_nurbaiti')
                    ->label('Tolak Bu Nurbaiti')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_NURBAITI' && ($user->isNurbaiti() || $user->isSuperAdmin());
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan Bu Nurbaiti')
                            ->placeholder('Tuliskan rincian yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan Perdin ditolak Bu Nurbaiti.')->warning()->send();
                    }),

                // 4.1.C Approval Thia Aisyah (Manajer Keuangan CV Top Selular)
                Tables\Actions\Action::make('approve_thia')
                    ->label('ACC Bu Thia Aisyah (Manajer Keuangan)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_THIA' && ($user->isThia() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Manajer Keuangan (Thia Aisyah)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan perjalanan dinas ini untuk diteruskan ke Finance CV Top Selular untuk proses pencairan dana?')
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
                        Notification::make()->title('Pengajuan Perdin disetujui Bu Thia Aisyah! Diteruskan ke antrean pencairan Finance CV Top Selular.')->success()->send();
                    }),

                Tables\Actions\Action::make('reject_thia')
                    ->label('Tolak Bu Thia Aisyah')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->approval_status === 'WAITING_THIA' && ($user->isThia() || $user->isSuperAdmin());
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan Bu Thia Aisyah')
                            ->placeholder('Tuliskan rincian yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan Perdin ditolak Bu Thia Aisyah.')->warning()->send();
                    }),

                // 4.2 Admin Kirim Ulang ke Finance setelah revisi data
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
                    ->modalHeading('Kirim Ulang Revisi Perdin ke Finance')
                    ->modalDescription('Apakah revisi data perdin sudah benar dan siap diajukan kembali ke Finance untuk pencairan?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'admin_approved_by_id' => $user?->id,
                            'admin_approved_at' => now(),
                        ]);
                        Notification::make()->title('Revisi pengajuan Perdin berhasil dikirimkan kembali ke Finance!')->success()->send();
                    }),

                // 5. Finance Action: Pencairan Dana & Upload Bukti Transfer (MSI atau Complete Selular)
                Tables\Actions\Action::make('disburse_perdin')
                    ->label('Cairkan Dana')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        if ($record->disbursement_status === 'Sudah Dicairkan') return false;
                        if ($record->approval_status !== 'DISETUJUI') return false;

                        if ($user->isAdmin() || $user->isSuperAdmin()) return true;

                        if ($user->isFinance()) {
                            if (!empty($user->badan_usaha) && !empty($record->badan_usaha)) {
                                return strtoupper($user->badan_usaha) === strtoupper($record->badan_usaha);
                            }
                            return true;
                        }

                        return false;
                    })
                    ->form([
                        Forms\Components\TextInput::make('kwitansi_number')
                            ->label('Nomor Kwitansi Resmi')
                            ->default(fn (Claim $record) => 'KWT-PERDIN-' . date('Ym') . '-' . str_pad($record->id, 4, '0', STR_PAD_LEFT))
                            ->required(),

                        Forms\Components\FileUpload::make('transfer_proof_photo')
                            ->label('Upload Bukti Transfer Bank')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('transfer-proofs')
                            ->visibility('public')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'kwitansi_number' => $data['kwitansi_number'],
                            'transfer_proof_photo' => $data['transfer_proof_photo'],
                            'disbursement_status' => 'Sudah Dicairkan',
                            'disbursed_at' => now(),
                        ]);
                        Notification::make()->title('Dana Perdin berhasil dicairkan! Kwitansi resmi telah dibuat.')->success()->send();
                    }),

                // 6. Cetak Kwitansi Resmi Perdin (Format Cyan Voucher)
                Tables\Actions\Action::make('print_kwitansi')
                    ->label('Kwitansi Makan')
                    ->icon('heroicon-o-receipt-percent')
                    ->color('info')
                    ->visible(fn (Claim $record) => (float)$record->meal_allowance > 0)
                    ->modalHeading('Kwitansi Resmi Uang Makan Perjalanan Dinas')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (Claim $record) => view('filament.modals.kwitansi-perdin', ['record' => $record])),

                // 7. Post-Trip: Pemohon Mengirimkan Nota Balik & Pengembalian Sisa Dana (Max H+1)
                Tables\Actions\Action::make('submit_nota_balik')
                    ->label(fn (Claim $record) => $record->nota_balik_status === 'Ditolak Finance' ? 'Revisi Nota Balik' : 'Kirim Nota Balik')
                    ->icon('heroicon-o-receipt-percent')
                    ->color(fn (Claim $record) => $record->nota_balik_status === 'Ditolak Finance' ? 'danger' : 'warning')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        $isApplicant = ($user->getEffectiveEmployeeId() == $record->employee_id);
                        $canSubmit = (!$record->nota_balik_submitted || $record->nota_balik_status === 'Ditolak Finance');
                        return ($record->disbursement_status === 'Sudah Dicairkan' || $record->approval_status === 'DISETUJUI') &&
                               $canSubmit &&
                               ($isApplicant || $user->isAdmin() || $user->isSuperAdmin());
                    })
                    ->modalHeading('Pertanggungjawaban Nota Balik & Realisasi Biaya')
                    ->modalDescription('Masukkan realisasi biaya riil yang telah terpakai. Sistem akan otomatis menghitung riwayat per biaya dan nominal sisa dana yang harus dikembalikan.')
                    ->modalSubmitActionLabel('Kirim Nota Balik ke Finance')
                    ->form([
                        Forms\Components\Placeholder::make('info_diajukan')
                            ->label('Ringkasan Dana Awal yang Dicairkan')
                            ->content(fn (Claim $record) => new \Illuminate\Support\HtmlString(
                                "<div class='text-sm space-y-1 bg-slate-50 dark:bg-slate-900/40 p-3 rounded border border-slate-200 dark:border-slate-800'>" .
                                "<div><strong>Total Uang Muka:</strong> Rp " . number_format($record->amount, 0, ',', '.') . "</div>" .
                                "<div class='text-xs text-slate-500'>Uang Makan: Rp " . number_format($record->meal_allowance, 0, ',', '.') . 
                                " | BBM: Rp " . number_format($record->fuel_cost, 0, ',', '.') . 
                                " | Tol/Tiket: Rp " . number_format($record->toll_cost, 0, ',', '.') . 
                                " | Sewa: Rp " . number_format($record->car_rental_cost, 0, ',', '.') . "</div>" .
                                ($record->nota_balik_rejection_reason ? "<div class='text-xs font-bold text-red-600 mt-2'>⚠️ Catatan Penolakan Finance Sebelumnya: {$record->nota_balik_rejection_reason}</div>" : "") .
                                "</div>"
                            )),

                        Forms\Components\Section::make('Rincian Realisasi Biaya yang Terpakai')
                            ->description('Ketikkan nominal yang benar-benar terpakai selama dinas:')
                            ->schema([
                                Forms\Components\TextInput::make('actual_meal_allowance')
                                    ->label('Realisasi Uang Makan (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(fn (Claim $record) => $record->actual_meal_allowance > 0 ? $record->actual_meal_allowance : $record->meal_allowance)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set, Claim $record) => self::recalculateNotaBalik($get, $set, $record)),

                                Forms\Components\TextInput::make('actual_fuel_cost')
                                    ->label('Realisasi BBM (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(fn (Claim $record) => $record->actual_fuel_cost > 0 ? $record->actual_fuel_cost : $record->fuel_cost)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set, Claim $record) => self::recalculateNotaBalik($get, $set, $record)),

                                Forms\Components\TextInput::make('actual_travel_cost')
                                    ->label('Realisasi Travel (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(fn (Claim $record) => $record->actual_travel_cost > 0 ? $record->actual_travel_cost : $record->travel_cost)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set, Claim $record) => self::recalculateNotaBalik($get, $set, $record)),

                                Forms\Components\TextInput::make('actual_car_rental_cost')
                                    ->label('Realisasi Sewa Motor (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(fn (Claim $record) => $record->actual_car_rental_cost > 0 ? $record->actual_car_rental_cost : $record->car_rental_cost)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set, Claim $record) => self::recalculateNotaBalik($get, $set, $record)),

                                Forms\Components\TextInput::make('actual_other_transport_cost')
                                    ->label('Realisasi Lainnya / Tol (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(fn (Claim $record) => ($record->actual_other_transport_cost > 0 ? $record->actual_other_transport_cost : ($record->actual_toll_cost > 0 ? $record->actual_toll_cost : ($record->other_transport_cost > 0 ? $record->other_transport_cost : $record->toll_cost))))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set, Claim $record) => self::recalculateNotaBalik($get, $set, $record)),
                            ])->columns(3),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('actual_amount_total')
                                    ->label('Total Biaya Terpakai (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(fn (Claim $record) => (float)($record->actual_amount_total > 0 ? $record->actual_amount_total : $record->amount)),

                                Forms\Components\TextInput::make('remaining_amount_calculated')
                                    ->label('Sisa Dana Pengembalian (Otomatis)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(fn (Claim $record) => (float)($record->remaining_amount_calculated ?: 0))
                                    ->extraAttributes(['class' => 'font-bold text-emerald-600']),
                            ]),

                        Forms\Components\Placeholder::make('info_kwitansi_otomatis')
                            ->label('Kwitansi Resmi Uang Makan')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-900">
                                    <strong><span class="inline-block mr-1">ℹ️</span> Kwitansi Makan Otomatis:</strong> Kwitansi Uang Makan resmi secara otomatis disertakan ke dalam berkas Nota Balik ini sebagai pengganti struk makan fisik. Anda tidak perlu mengunggah struk makan.
                                </div>
                            ')),

                        Forms\Components\Placeholder::make('info_pengembalian_dana')
                            ->label('Ketentuan Sisa Dana')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-900">
                                    <strong><span class="inline-block mr-1">⚠️</span> Bukti Transfer Belum Perlu Diunggah:</strong> Silakan unggah seluruh bukti nota fisik transportasi/BBM/tiket terlebih dahulu. Setelah Finance memverifikasi seluruh bukti, sistem akan mengirimkan notifikasi petunjuk pengembalian sisa dana (jika ada).
                                </div>
                            ')),

                        Forms\Components\FileUpload::make('photos')
                            ->label('Upload Seluruh Foto Bukti Nota Fisik Pengeluaran (Tiket, Struk SPBU, Tol)')
                            ->image()
                            ->multiple()
                            ->disk('public')
                            ->directory('claim-photos')
                            ->visibility('public')
                            ->helperText('Upload foto struk BBM dari SPBU, tiket, atau karcis tol. Kwitansi uang makan otomatis disertakan oleh sistem.')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $actualMeal = (float)($data['actual_meal_allowance'] ?? $record->meal_allowance);
                        $actualFuel = (float)($data['actual_fuel_cost'] ?? $record->fuel_cost);
                        $actualTravel = (float)($data['actual_travel_cost'] ?? $record->travel_cost);
                        $actualRental = (float)($data['actual_car_rental_cost'] ?? $record->car_rental_cost);
                        $actualOther = (float)($data['actual_other_transport_cost'] ?? ($data['actual_toll_cost'] ?? $record->toll_cost));

                        $actualTotal = $actualMeal + $actualFuel + $actualTravel + $actualRental + $actualOther;
                        $remaining = max(0, ((float)$record->amount) - $actualTotal);

                        $record->update([
                            'actual_meal_allowance' => $actualMeal,
                            'actual_fuel_cost' => $actualFuel,
                            'actual_travel_cost' => $actualTravel,
                            'actual_car_rental_cost' => $actualRental,
                            'actual_other_transport_cost' => $actualOther,
                            'actual_toll_cost' => $actualOther,
                            'actual_amount_total' => $actualTotal,
                            'remaining_amount_calculated' => $remaining,
                            'photos' => $data['photos'] ?? $record->photos,
                            'nota_balik_submitted' => true,
                            'nota_balik_submitted_at' => now(),
                            'nota_balik_status' => 'Menunggu Verifikasi Finance',
                            'nota_balik_rejection_reason' => null,
                        ]);

                        Notification::make()
                            ->title('Nota Balik Berhasil Diserahkan!')
                            ->body("Realisasi Rp " . number_format($actualTotal, 0, ',', '.') . " | Sisa Dana: Rp " . number_format($remaining, 0, ',', '.') . ". Kwitansi uang makan otomatis terlampir. Menunggu verifikasi tim Finance.")
                            ->success()
                            ->send();
                    }),

                // 8. Finance / Admin: ACC Verifikasi Nota Balik
                Tables\Actions\Action::make('approve_nota_balik_finance')
                    ->label('ACC Nota Balik')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->nota_balik_submitted &&
                               $record->nota_balik_status !== 'Disetujui Finance' &&
                               ($user->isFinance() || $user->isAdmin() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi & ACC Nota Balik Perdin')
                    ->modalDescription(function (Claim $record) {
                        $desc = "Apakah seluruh nota fisik dan rincian realisasi (Total: Rp " . number_format((float)$record->actual_amount_total, 0, ',', '.') . ") telah diverifikasi sesuai?";
                        if ((float)$record->remaining_amount_calculated > 0) {
                            $desc .= " Terdapat sisa dana Rp " . number_format((float)$record->remaining_amount_calculated, 0, ',', '.') . " yang akan dinotifikasikan ke pemohon untuk dikembalikan.";
                        }
                        return $desc;
                    })
                    ->action(function (Claim $record) {
                        $record->update([
                            'nota_balik_status' => 'Disetujui Finance',
                            'nota_balik_finance_sent' => true,
                            'nota_balik_finance_sent_at' => now(),
                        ]);

                        // Item 9: Berikan notifikasi jika terdapat sisa dana pengembalian
                        $remaining = (float)$record->remaining_amount_calculated;
                        if ($remaining > 0) {
                            $remainingFmt = 'Rp ' . number_format($remaining, 0, ',', '.');
                            $recipient = $record->user ?? ($record->employee?->user);
                            if ($recipient) {
                                Notification::make()
                                    ->title('Verifikasi Selesai: Pengembalian Sisa Dana Perdin')
                                    ->body("Nota balik UID {$record->uid} telah diverifikasi. Terdapat sisa dana sebesar {$remainingFmt} yang harus dikembalikan ke rekening kas Finance.")
                                    ->warning()
                                    ->sendToDatabase($recipient);
                            }
                        }

                        Notification::make()
                            ->title('Nota Balik Disetujui Finance!')
                            ->body('Pertanggungjawaban perdin telah dinyatakan lengkap dan disetujui.' . ($remaining > 0 ? ' Notifikasi pengembalian dana telah dikirim ke pemohon.' : ''))
                            ->success()
                            ->send();
                    }),

                // 8.1 Finance / Admin: Tolak Nota Balik dengan Catatan (Item 16)
                Tables\Actions\Action::make('reject_nota_balik_finance')
                    ->label('Tolak Nota Balik')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->nota_balik_submitted &&
                               $record->nota_balik_status !== 'Disetujui Finance' &&
                               ($user->isFinance() || $user->isAdmin() || $user->isSuperAdmin());
                    })
                    ->form([
                        Forms\Components\Textarea::make('nota_balik_rejection_reason')
                            ->label('Alasan Penolakan Nota Balik')
                            ->placeholder('Contoh: Foto nota BBM tidak jelas / Foto bukti transfer pengembalian sisa dana belum dilampirkan / Jumlah tidak sesuai...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'nota_balik_status' => 'Ditolak Finance',
                            'nota_balik_rejection_reason' => $data['nota_balik_rejection_reason'],
                            'nota_balik_submitted' => false,
                        ]);

                        Notification::make()
                            ->title('Nota Balik Ditolak Finance')
                            ->body('Catatan perbaikan telah dikirimkan ke pemohon.')
                            ->danger()
                            ->send();
                    }),

                \App\Filament\Actions\ViewTransferProofAction::make(),

                // 9. Admin Region: Export PDF Form Perdin (Item 6) & Nota Balik (Item 15)
                Tables\Actions\Action::make('export_pdf')
                    ->label('PDF Perdin')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn (Claim $record) => app(\App\Services\ClaimPdfExportService::class)->exportPerdinSinglePdf($record)),

                Tables\Actions\Action::make('export_nota_balik_pdf')
                    ->label('PDF Nota Balik')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(fn (Claim $record) => $record->nota_balik_submitted || (float)$record->actual_amount_total > 0)
                    ->action(fn (Claim $record) => app(\App\Services\ClaimPdfExportService::class)->exportNotaBalikSinglePdf($record)),

                Tables\Actions\Action::make('export_nota_balik_excel')
                    ->label('Excel Nota Balik')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (Claim $record) => $record->nota_balik_submitted || (float)$record->actual_amount_total > 0)
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportNotaBalikSingleExcel($record)),

                Tables\Actions\Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),

                Tables\Actions\Action::make('download_perdin_excel')
                    ->label('Form SPT/Perdin')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportPerdinForm($record)),

                Tables\Actions\EditAction::make()
                    ->visible(fn (Claim $record) => static::canEdit($record)),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Claim $record) => static::canDelete($record)),
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
            'index' => Pages\ListPerdinClaims::route('/'),
            'create' => Pages\CreatePerdinClaim::route('/create'),
            'edit' => Pages\EditPerdinClaim::route('/{record}/edit'),
        ];
    }
}

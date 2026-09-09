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

    protected static ?string $modelLabel = 'Klaim Perjalanan Dinas';

    protected static ?string $pluralModelLabel = 'Klaim Perjalanan Dinas (Perdin)';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance()) return false;
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isAsm() || $user->isRgm() || $user->isSales() || $user->isJejen();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isFinance()) return false;
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isFinance()) return false;

        // Jika sudah di-approved atau dana dicairkan, TIDAK DAPAT DI-EDIT (dikunci / read-only)
        if (in_array($record->approval_status, ['ACC_ASM', 'ACC_RGM', 'ACC_PAK_JEJEN', 'DISETUJUI']) || $record->disbursement_status === 'Sudah Dicairkan') {
            return false;
        }

        // Dapat diedit saat status DRAFT, DIAJUKAN, DITOLAK, atau SEDANG_DIREVISI
        return true;
    }

    /**
     * Cek apakah user adalah ASM atasan langsung dari pemohon klaim
     */
    public static function isAsmSupervisorOf(?\App\Models\User $user, Claim $record): bool
    {
        if (!$user) return false;
        if ($user->isSuperAdmin() || $user->isAdmin()) return true;
        if (!$user->isAsm()) return false;

        $applicantEmp = $record->effective_employee;
        if (!$applicantEmp) return false;

        $asmEmpId = $user->getEffectiveEmployeeId();
        if ($asmEmpId && $applicantEmp->supervisor_id == $asmEmpId) {
            return true;
        }

        // Fallback: jika supervisor belum di-set, cek kecocokan region
        if (!$applicantEmp->supervisor_id) {
            $userRegions = $user->getRegionList();
            if ($applicantEmp->region && in_array(strtoupper($applicantEmp->region), $userRegions)) {
                return true;
            }
        }

        return false;
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

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isFinance()) {
            return $query->where('approval_status', 'DIAJUKAN');
        }

        if ($user->isAdmin()) {
            return $query->visibleToUser($user);
        }

        $empId = $user->getEffectiveEmployeeId();
        if ($user->isAsm()) {
            // ASM melihat pengajuannya sendiri + pengajuan dari Sales bawahannya langsung
            $query->where(function ($q) use ($user, $empId) {
                $q->where('user_id', $user->id);
                if ($empId) {
                    $q->orWhere('employee_id', $empId)
                      ->orWhereHas('employee', fn ($eq) => $eq->where('supervisor_id', $empId));
                }
                $regions = $user->getRegionList();
                if (!empty($regions)) {
                    $q->orWhereHas('employee', fn ($eq) => $eq->whereIn('region', $regions));
                }
            });
        } elseif ($user->isRgm()) {
            // RGM melihat pengajuannya sendiri + pengajuan dari ASM bawahannya + Sales di bawah ASM tersebut
            $query->where(function ($q) use ($user, $empId) {
                $q->where('user_id', $user->id);
                if ($empId) {
                    $q->orWhere('employee_id', $empId)
                      ->orWhereHas('employee', fn ($eq) => $eq->where('supervisor_id', $empId))
                      ->orWhereHas('employee.supervisor', fn ($sq) => $sq->where('supervisor_id', $empId));
                }
                $regions = $user->getRegionList();
                if (!empty($regions)) {
                    $q->orWhereHas('employee', fn ($eq) => $eq->whereIn('region', $regions));
                }
            });
        } elseif ($user->isJejen()) {
            $query->where(function ($q) use ($user, $empId) {
                $q->where('user_id', $user->id)
                  ->orWhere('approval_status', 'ACC_RGM');
                if ($empId) {
                    $q->orWhere('employee_id', $empId);
                }
            });
        } else {
            $query->where(function ($q) use ($user, $empId) {
                $q->where('user_id', $user->id);
                if ($empId) {
                    $q->orWhere('employee_id', $empId);
                }
            });
        }

        return $query;
    }

    /**
     * Otomatisasi perhitungan dana perdin dari lama hari dan rincian biaya
     */
    public static function recalculatePerdin(Forms\Get $get, Forms\Set $set, ?int $daysOverride = null, ?int $nightsOverride = null): void
    {
        $empId = $get('employee_id') ?: auth()->user()?->getEffectiveEmployeeId();
        $mealRate = 100000.0;
        $lodgingRate = 250000.0;

        if ($empId) {
            $emp = Employee::with('role')->find($empId);
            if ($emp) {
                $mealRate = $emp->perdin_meal_allowance > 0 ? (float)$emp->perdin_meal_allowance : (float)($emp->role?->meal_allowance_per_day ?? 100000);
                $lodgingRate = $emp->perdin_lodging_allowance > 0 ? (float)$emp->perdin_lodging_allowance : (float)($emp->role?->lodging_allowance_per_night ?? 250000);
            }
        }

        // Tentukan lama hari (minimal 1 hari)
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

        // Tentukan jumlah malam menginap (hari - 1, jika 1 hari PP = 0 malam)
        if ($nightsOverride !== null) {
            $nights = max(0, $nightsOverride);
        } else {
            $nights = max(0, $days - 1);
        }

        $set('days_count', $days);
        $set('nights_count', $nights);

        $meal = (float)($days * $mealRate);
        $lodging = (float)($nights * $lodgingRate);

        $set('meal_allowance', $meal);
        $set('lodging_allowance', $lodging);

        $toll = (float)($get('toll_cost') ?? 0);
        $fuel = (float)($get('fuel_cost') ?? 0);
        $rental = (float)($get('car_rental_cost') ?? 0);

        $total = $meal + $lodging + $toll + $fuel + $rental;
        $set('amount', $total);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->disabled(fn (?Claim $record) => $record && !static::canEdit($record))
            ->schema([
                Forms\Components\Hidden::make('user_id')->default(fn () => auth()->id()),
                Forms\Components\Hidden::make('claim_category')->default('perdin'),
                Forms\Components\Hidden::make('is_perdin')->default(true),

                // TOP SECTION: _UID EXTERNAL & STATUS PENGAJUAN (Hanya untuk Admin / Finance / Super Admin)
                Forms\Components\Section::make('_UID EXTERNAL & STATUS PENGAJUAN')
                    ->description('Nomor referensi ID Form External dan status approval sistem. Terekam di awal formulir.')
                    ->icon('heroicon-o-identification')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin() || auth()->user()?->isFinance())
                    ->schema([
                        Forms\Components\TextInput::make('_uid')
                            ->label('_UID (ID Form External)')
                            ->placeholder('Contoh: UID-PERDIN-2026-001')
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

                Forms\Components\Section::make('Informasi Pemohon & Tujuan Perjalanan Dinas')
                    ->description('Pilih karyawan pemohon dan rute cabang tujuan dinas')
                    ->icon('heroicon-o-user')
                    ->schema([
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
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                if ($state) {
                                    $emp = Employee::with(['role', 'positionModel'])->find($state);
                                    if ($emp) {
                                        $set('homebase', $emp->homebase ?? 'Purwokerto');
                                        $transportRate = $emp->perdin_transport_budget > 0 ? $emp->perdin_transport_budget : ($emp->role?->toll_allowance ?? 500000);
                                        if (empty($get('toll_cost')) || (float)$get('toll_cost') == 0) {
                                            $set('toll_cost', (float)$transportRate);
                                        }
                                    }
                                }
                                self::recalculatePerdin($get, $set);
                            })
                            ->required(),

                        Forms\Components\Select::make('branch_id')
                            ->label('Cabang Tujuan')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $branch = Branch::find($state);
                                    if ($branch) {
                                        $set('destination_city', $branch->city);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('homebase')
                            ->label('Kota Asal (Homebase)')
                            ->default('Purwokerto')
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Jadwal Perjalanan Dinas & Batas Nota Balik')
                    ->description('Tentukan tanggal keberangkatan dan tanggal kepulangan. Total hari otomatis menghitung tunjangan uang makan & penginapan.')
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

                        Forms\Components\TextInput::make('destination_city')
                            ->label('Kota Tujuan Perdin')
                            ->required()
                            ->placeholder('Contoh: Majenang / Cirebon / Semarang'),

                        Forms\Components\TextInput::make('distance_km')
                            ->label('Jarak Perjalanan (KM)')
                            ->numeric()
                            ->suffix('KM')
                            ->default(85)
                            ->required()
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
                            ->helperText('Otomatis menghitung Uang Makan & Penginapan')
                            ->required(),

                        Forms\Components\TextInput::make('nights_count')
                            ->label('Jumlah Malam Menginap')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                self::recalculatePerdin($get, $set, (int)($get('days_count') ?? 1), (int)$state);
                            })
                            ->dehydrateStateUsing(fn ($state) => max(0, (int)($state ?? 0)))
                            ->helperText('0 jika Pulang-Pergi (PP)')
                            ->required(),

                        Forms\Components\TextInput::make('purpose')
                            ->label('Keperluan / Agenda Perjalanan Dinas')
                            ->default('VISIT DEALER & OPERASIONAL CABANG')
                            ->columnSpanFull()
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Rincian Estimasi Biaya Perjalanan Dinas')
                    ->description('Tunjangan uang makan dan penginapan otomatis dihitung dari lama hari/malam. Total estimasi otomatis diakumulasi.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('meal_allowance')
                                    ->label('1. Tunjangan Uang Makan (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(function (Forms\Get $get) {
                                        $empId = $get('employee_id') ?: auth()->user()?->getEffectiveEmployeeId();
                                        if ($empId) {
                                            $emp = Employee::with('role')->find($empId);
                                            if ($emp) {
                                                return (float)($emp->perdin_meal_allowance > 0 ? $emp->perdin_meal_allowance : ($emp->role?->meal_allowance_per_day ?? 100000));
                                            }
                                        }
                                        return 100000.0;
                                    })
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        $meal = (float)($get('meal_allowance') ?? 0);
                                        $lodging = (float)($get('lodging_allowance') ?? 0);
                                        $toll = (float)($get('toll_cost') ?? 0);
                                        $fuel = (float)($get('fuel_cost') ?? 0);
                                        $rental = (float)($get('car_rental_cost') ?? 0);
                                        $set('amount', $meal + $lodging + $toll + $fuel + $rental);
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (float)($state ?? 0))
                                    ->helperText('Otomatis: Lama Hari x Tarif Makan Karyawan'),

                                Forms\Components\TextInput::make('lodging_allowance')
                                    ->label('2. Tunjangan Penginapan Hotel (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        $meal = (float)($get('meal_allowance') ?? 0);
                                        $lodging = (float)($get('lodging_allowance') ?? 0);
                                        $toll = (float)($get('toll_cost') ?? 0);
                                        $fuel = (float)($get('fuel_cost') ?? 0);
                                        $rental = (float)($get('car_rental_cost') ?? 0);
                                        $set('amount', $meal + $lodging + $toll + $fuel + $rental);
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (float)($state ?? 0))
                                    ->helperText('Otomatis: Malam x Tarif Hotel (Rp 0 jika PP)'),

                                Forms\Components\TextInput::make('toll_cost')
                                    ->label('3. Transportasi - Tiket / Tol (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        $meal = (float)($get('meal_allowance') ?? 0);
                                        $lodging = (float)($get('lodging_allowance') ?? 0);
                                        $toll = (float)($get('toll_cost') ?? 0);
                                        $fuel = (float)($get('fuel_cost') ?? 0);
                                        $rental = (float)($get('car_rental_cost') ?? 0);
                                        $set('amount', $meal + $lodging + $toll + $fuel + $rental);
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (float)($state ?? 0)),

                                Forms\Components\TextInput::make('fuel_cost')
                                    ->label('4. Transportasi - Bensin / BBM (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        $meal = (float)($get('meal_allowance') ?? 0);
                                        $lodging = (float)($get('lodging_allowance') ?? 0);
                                        $toll = (float)($get('toll_cost') ?? 0);
                                        $fuel = (float)($get('fuel_cost') ?? 0);
                                        $rental = (float)($get('car_rental_cost') ?? 0);
                                        $set('amount', $meal + $lodging + $toll + $fuel + $rental);
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (float)($state ?? 0)),

                                Forms\Components\TextInput::make('car_rental_cost')
                                    ->label('5. Kompensasi Sewa Kendaraan (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        $meal = (float)($get('meal_allowance') ?? 0);
                                        $lodging = (float)($get('lodging_allowance') ?? 0);
                                        $toll = (float)($get('toll_cost') ?? 0);
                                        $fuel = (float)($get('fuel_cost') ?? 0);
                                        $rental = (float)($get('car_rental_cost') ?? 0);
                                        $set('amount', $meal + $lodging + $toll + $fuel + $rental);
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (float)($state ?? 0)),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Total Estimasi Dana Perdin (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->live()
                                    ->default(function (Forms\Get $get) {
                                        $empId = $get('employee_id') ?: auth()->user()?->getEffectiveEmployeeId();
                                        $meal = 100000.0;
                                        if ($empId) {
                                            $emp = Employee::with('role')->find($empId);
                                            if ($emp) {
                                                $meal = (float)($emp->perdin_meal_allowance > 0 ? $emp->perdin_meal_allowance : ($emp->role?->meal_allowance_per_day ?? 100000));
                                            }
                                        }
                                        return $meal;
                                    })
                                    ->helperText('Otomatis dihitung dari Lama Hari: (Uang Makan x Hari) + (Penginapan x Malam) + Tiket/Tol + BBM + Sewa Kendaraan')
                                    ->dehydrateStateUsing(fn ($state, Forms\Get $get) => (float)($state ?: ((float)($get('meal_allowance') ?? 0) + (float)($get('lodging_allowance') ?? 0) + (float)($get('toll_cost') ?? 0) + (float)($get('fuel_cost') ?? 0) + (float)($get('car_rental_cost') ?? 0)))),
                            ]),
                    ]),

                Forms\Components\Section::make('Lampiran Bukti Tiket / Hotel / Nota Perdin')
                    ->description('Lampirkan foto tiket transportasi, kwitansi hotel, atau nota pendukung perdin.')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('photos')
                            ->label('Foto Tiket / Kwitansi Hotel / Struk Nota')
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
                    ->label('Tgl Berangkat')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('perdin_return_date')
                    ->label('Tgl Pulang')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('kwitansi_number')
                    ->label('No. Kwitansi')
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->searchable(),

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
                    ->label('Status Approval')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'DIAJUKAN' => 'warning',
                        'ACC_ASM' => 'info',
                        'ACC_RGM' => 'primary',
                        'ACC_PAK_JEJEN', 'DISETUJUI' => 'success',
                        'SEDANG_DIREVISI' => 'warning',
                        'DITOLAK' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (Claim $record) => !empty($record->rejection_reason) ? "Catatan: {$record->rejection_reason}" : null),

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

                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Status Approval')
                    ->options([
                        'DIAJUKAN' => 'DIAJUKAN (Menunggu Approval)',
                        'ACC_ASM' => 'ACC_ASM (Disetujui ASM)',
                        'ACC_RGM' => 'ACC_RGM (Disetujui RGM)',
                        'ACC_PAK_JEJEN' => 'ACC_PAK_JEJEN (Disetujui Pak Jejen)',
                        'DISETUJUI' => 'DISETUJUI (Selesai)',
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
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_ASM',
                            'approved_by_asm_id' => $user?->id,
                            'approved_by_asm_at' => now(),
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
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_RGM',
                            'approved_by_rgm_id' => $user?->id,
                            'approved_by_rgm_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan Perdin diizinkan oleh RGM! Siap diajukan ke Management.')->success()->send();
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

                // 3. Approval Action: ACC Pak Jejen (Head of Sales)
                Tables\Actions\Action::make('acc_jejen')
                    ->label('Acc Pak Jejen')
                    ->icon('heroicon-o-star')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return in_array($record->approval_status, ['ACC_RGM', 'DIAJUKAN']) && ($user->isJejen() || $user->isAdmin() || $user->isSuperAdmin());
                    })
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_PAK_JEJEN',
                            'approved_by_jejen_id' => $user?->id,
                            'approved_by_jejen_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan Perdin di-ACC oleh Pak Jejen!')->success()->send();
                    }),

                // 4. Admin Action: Ajukan ke Google Form (Pak Jejen & Pak Yoga)
                Tables\Actions\Action::make('submit_to_gform')
                    ->label('Ajukan ke G-Form Atasan')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return !$record->admin_gform_submitted && 
                               ($user->isAdmin() || $user->isSuperAdmin()) &&
                               in_array($record->approval_status, ['ACC_RGM', 'ACC_PAK_JEJEN', 'DISETUJUI', 'DIAJUKAN']);
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

                // 5. Finance Action: Pencairan Dana & Upload Bukti Transfer
                Tables\Actions\Action::make('disburse_perdin')
                    ->label('Cairkan Dana')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return ($user->isFinance() || $user->isAdmin() || $user->isSuperAdmin()) &&
                               $record->disbursement_status !== 'Sudah Dicairkan';
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
                    ->label('Cetak Kwitansi')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->visible(fn (Claim $record) => $record->disbursement_status === 'Sudah Dicairkan' || !empty($record->transfer_proof_photo))
                    ->modalHeading('Kwitansi Resmi Perjalanan Dinas')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (Claim $record) => view('filament.modals.kwitansi-perdin', ['record' => $record])),

                // 7. Post-Trip: Pemohon Mengirimkan Nota Balik & Pengembalian Sisa Dana (Max H+1)
                Tables\Actions\Action::make('submit_nota_balik')
                    ->label('Kirim Nota Balik')
                    ->icon('heroicon-o-receipt-percent')
                    ->color('warning')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        $isApplicant = ($user->getEffectiveEmployeeId() == $record->employee_id);
                        return $record->disbursement_status === 'Sudah Dicairkan' &&
                               !$record->nota_balik_submitted &&
                               ($isApplicant || $user->isAdmin() || $user->isSuperAdmin());
                    })
                    ->form([
                        Forms\Components\Placeholder::make('deadline_info')
                            ->label('Batas Akhir Penyerahan Nota Balik')
                            ->content(fn (Claim $record) => 'Maksimal H+1: ' . ($record->perdin_return_deadline ? $record->perdin_return_deadline->translatedFormat('d F Y') : '-')),

                        Forms\Components\FileUpload::make('photos')
                            ->label('Upload Seluruh Foto Bukti Nota Fisik Pengeluaran (Tiket, Bon Makan, Hotel, Tol)')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->multiple()
                            ->disk('public')
                            ->directory('claim-photos')
                            ->visibility('public')
                            ->required(),

                        Forms\Components\TextInput::make('return_transfer_amount')
                            ->label('Nominal Sisa Dana Dikembalikan ke Finance (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->helperText('Isi jika ada kelebihan uang muka yang dikembalikan ke rekening Finance'),

                        Forms\Components\FileUpload::make('return_transfer_proof')
                            ->label('Foto Bukti Transfer Pengembalian Sisa Dana ke Finance')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('claim-transfer-proofs')
                            ->visibility('public')
                            ->helperText('Wajib dilampirkan jika terdapat sisa dana yang dikembalikan'),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'photos' => $data['photos'] ?? $record->photos,
                            'return_transfer_amount' => $data['return_transfer_amount'] ?? 0,
                            'return_transfer_proof' => $data['return_transfer_proof'] ?? null,
                            'nota_balik_submitted' => true,
                            'nota_balik_submitted_at' => now(),
                        ]);
                        Notification::make()->title('Nota balik dan bukti sisa dana berhasil dikirim ke Admin!')->success()->send();
                    }),

                // 8. Admin Region: Verifikasi Selesai Nota Balik & Sisa Dana
                Tables\Actions\Action::make('verify_admin_perdin')
                    ->label('Verifikasi Selesai (ACC)')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->nota_balik_submitted &&
                               $record->approval_status !== 'DISETUJUI' &&
                               ($user->isAdmin() || $user->isSuperAdmin());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi Selesai Pertanggungjawaban Perjalanan Dinas')
                    ->modalDescription('Pastikan nota fisik, tiket, dan bukti transfer pengembalian sisa dana ke Finance telah diverifikasi lengkap dan sesuai.')
                    ->action(function (Claim $record) {
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'nota_balik_finance_sent' => true,
                            'nota_balik_finance_sent_at' => now(),
                        ]);
                        Notification::make()->title('Pertanggungjawaban Perjalanan Dinas selesai dan telah disetujui Admin!')->success()->send();
                    }),

                // 8.1 Admin Region: Minta Perbaikan Nota Balik
                Tables\Actions\Action::make('reject_nota_balik')
                    ->label('Minta Perbaikan Nota')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        return $record->nota_balik_submitted &&
                               $record->approval_status !== 'DISETUJUI' &&
                               ($user->isAdmin() || $user->isSuperAdmin());
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Catatan Perbaikan Nota Balik')
                            ->placeholder('Jelaskan nota fisik, tiket, atau bukti transfer sisa dana yang perlu diperbaiki oleh pemohon...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'nota_balik_submitted' => false,
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Permintaan perbaikan nota balik telah dikirimkan ke pemohon.')->warning()->send();
                    }),

                \App\Filament\Actions\ViewTransferProofAction::make(),

                // 9. Admin Region: Export Excel Realisasi Biaya (Format Voucher Resmi Standar Sama dengan Transport/Entertain)
                Tables\Actions\Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
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
                    ->visible(fn (Claim $record) => in_array($record->approval_status, ['DRAFT', 'DITOLAK'])),
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

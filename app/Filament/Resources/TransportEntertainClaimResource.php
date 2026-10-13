<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransportEntertainClaimResource\Pages;
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

class TransportEntertainClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'PENGAJUAN KLAIM';

    protected static ?string $modelLabel = 'Klaim Transportasi & Entertain';

    protected static ?string $pluralModelLabel = 'Klaim Transport & Entertain';

    protected static ?int $navigationSort = 2;

    /**
     * ASM, RGM, Admin, SuperAdmin dapat mengakses klaim Transport & Entertain (Sales TIDAK BISA KLAIM ENTERTAIN & LAINNYA)
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
                $q->where('claim_category', 'transport_entertain')
                  ->orWhere(function ($sub) {
                      $sub->whereNull('claim_category')
                          ->where('is_perdin', false)
                          ->where(function ($typeQ) {
                              $typeQ->where('claim_type', 'like', '%Entertain%')
                                    ->orWhere('claim_type', 'like', '%Transport%')
                                    ->orWhere('claim_type', 'like', '%Tol%')
                                    ->orWhere('claim_type', 'like', '%Parkir%')
                                    ->orWhere('items', 'like', '%Entertain%')
                                    ->orWhere('items', 'like', '%Transport%')
                                    ->orWhere('items', 'like', '%Tol%')
                                    ->orWhere('items', 'like', '%Parkir%');
                          });
                  });
            })
            ->where(function ($excludeQ) {
                $excludeQ->whereNull('claim_category')
                         ->orWhereNotIn('claim_category', ['bbm', 'ba_bbm']);
            })
            ->whereNull('bbm_photo_combined')
            ->where(function ($q) {
                $q->whereNull('claim_type')
                  ->orWhere('claim_type', 'not like', '%BBM%');
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
                Forms\Components\Hidden::make('claim_category')->default('transport_entertain'),

                // TOP SECTION: _UID EXTERNAL & STATUS PENGAJUAN (Hanya untuk Admin / Finance / Super Admin)
                Forms\Components\Section::make('_UID EXTERNAL & STATUS PENGAJUAN')
                    ->description('Nomor referensi ID Form External dan status approval sistem. Terekam di awal formulir.')
                    ->icon('heroicon-o-identification')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin() || auth()->user()?->isFinance())
                    ->schema([
                        Forms\Components\TextInput::make('_uid')
                            ->label('_UID (ID Form External)')
                            ->placeholder('Contoh: UID-TE-2026-001')
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

                // Notice Banner Khusus Role ASM & RGM
                Forms\Components\Section::make('Petunjuk Pengajuan Biaya Entertain (Makan & Ultah)')
                    ->icon('heroicon-o-information-circle')
                    ->visible(fn () => auth()->user()?->isAsm() || auth()->user()?->isRgm())
                    ->schema([
                        Forms\Components\Placeholder::make('asm_rgm_entertain_notice')
                            ->hiddenLabel()
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="flex items-start gap-2.5 p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-900 dark:text-emerald-100 text-sm">
                                    <span class="text-2xl">📋</span>
                                    <div>
                                        <p class="font-bold text-emerald-800 dark:text-emerald-200 text-sm">Khusus Pengajuan Biaya Makan & Ulang Tahun:</p>
                                        <p class="text-xs text-emerald-700 dark:text-emerald-300 mt-0.5">Pilih kategori <strong>Makan</strong> atau <strong>Ulang Tahun</strong> pada rincian transaksi di bawah. Bagian <strong>Daftar Hadir</strong> akan otomatis terbuka untuk Anda isi. Data daftar hadir akan otomatis tersimpan dan masuk ke data Admin.</p>
                                    </div>
                                </div>
                            '))
                    ]),

                Forms\Components\Section::make('Informasi Pemohon & Cabang')
                    ->description('Pilih user/pemohon klaim dan cabang terkait')
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
                            ->label('Pilih Branch / Cabang')
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
                                            // Propagate branch city to empty repeater items as well
                                            $items = $get('items') ?? [];
                                            if (is_array($items)) {
                                                foreach ($items as $k => $it) {
                                                    if (empty($it['city'])) {
                                                        $set("items.{$k}.city", $branch->city);
                                                    }
                                                }
                                            }
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
                            ->label('Kota')
                            ->placeholder('Contoh: Purwokerto / Cilacap')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                if ($state) {
                                    $items = $get('items') ?? [];
                                    if (is_array($items)) {
                                        foreach ($items as $k => $it) {
                                            if (empty($it['city'])) {
                                                $set("items.{$k}.city", $state);
                                            }
                                        }
                                    }
                                }
                            }),
                    ])->columns(3),

                Forms\Components\Section::make('Rekening Tujuan Transfer Lain (Opsional - Toko Bunga / Vendor)')
                    ->description('Jika klaim berupa karangan bunga atau ingin ditransfer ke rekening toko/pihak lain, isi data rekening di bawah.')
                    ->icon('heroicon-o-banknotes')
                    ->collapsed(true)
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('custom_bank_account_name')
                            ->label('Nama Pemilik Rekening Tujuan Lain')
                            ->placeholder('Contoh: Toko Bunga Florist Lestari / Vendor')
                            ->helperText('Opsional. Kosongkan jika transfer ditujukan ke rekening pribadi pemohon.'),

                        Forms\Components\TextInput::make('custom_bank_name')
                            ->label('Nama Bank Tujuan')
                            ->placeholder('Contoh: BCA / Mandiri / BRI')
                            ->datalist(['BCA', 'MANDIRI', 'BRI', 'BNI', 'BSI', 'CIMB NIAGA', 'PERMATA', 'DANAMON', 'JAGO', 'SEABANK']),

                        Forms\Components\TextInput::make('custom_bank_account_number')
                            ->label('Nomor Rekening Tujuan')
                            ->placeholder('Contoh: 1234567890'),
                    ])->columns(3),

                Forms\Components\Section::make('Rincian Transaksi Transportasi & Entertain (Bisa Multi-Pilih)')
                    ->description('Tambahkan rincian pengeluaran: Entertain (Makan/Lainnya), Transportasi, Tol, Tiket, Parkir, dsb.')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Daftar Item Transaksi')
                            ->schema([
                                Forms\Components\Grid::make([
                                    'default' => 1,
                                    'sm' => 2,
                                    'lg' => 4,
                                ])->schema([
                                    Forms\Components\DatePicker::make('receipt_date')
                                        ->label('Tanggal Nota')
                                        ->default(now())
                                        ->native(false)
                                        ->closeOnDateSelection()
                                        ->displayFormat('d/m/Y')
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                            if (blank($get('attendance_date'))) {
                                                $set('attendance_date', $state);
                                            }
                                        })
                                        ->required(),

                                    Forms\Components\Select::make('claim_type')
                                        ->label('Jenis Biaya')
                                        ->options([
                                            'Entertain' => 'Entertain',
                                            'Transportasi' => 'Transportasi / Tiket',
                                            'Tol / Parkir' => 'Tol / Parkir',
                                        ])
                                        ->default('Entertain')
                                        ->reactive()
                                        ->required(),

                                    Forms\Components\Select::make('entertain_subtype')
                                        ->label('Kategori Entertain')
                                        ->options([
                                            'Makan' => 'Makan (Jamuan Makan / Pertemuan Tim)',
                                            'Ulang Tahun' => 'Ulang Tahun (Perayaan Ultah / Kue Ultah)',
                                            'Karangan Bunga' => 'Karangan Bunga (Duka Cita / Ucapan / Grand Opening)',
                                            'Lainnya' => 'Lainnya (Hadiah, Cinderamata, dll.)',
                                        ])
                                        ->default('Makan')
                                        ->reactive()
                                        ->visible(fn (Forms\Get $get) => $get('claim_type') === 'Entertain')
                                        ->helperText('Makan & Ulang Tahun: form Daftar Hadir di bawah. Karangan Bunga: tersedia opsi rekening tujuan lain.'),

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
                                        ->placeholder('Contoh: 150000'),
                                ]),

                                Forms\Components\Grid::make([
                                    'default' => 1,
                                    'sm' => 2,
                                    'md' => 3,
                                ])->schema([
                                    Forms\Components\TextInput::make('purpose')
                                        ->label('Keperluan')
                                        ->required(fn (Forms\Get $get) => !in_array($get('claim_type'), ['Tol / Parkir', 'Tol', 'Parkir']))
                                        ->placeholder('Contoh: Meetup owner dealer / Tiket travel')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                            if (blank($get('attendance_purpose'))) {
                                                $set('attendance_purpose', $state);
                                            }
                                        }),

                                    Forms\Components\TextInput::make('note')
                                        ->label('Lokasi / Tempat / Toko')
                                        ->required()
                                        ->placeholder('Contoh: Kafe Tomoro / Stasiun Purwokerto')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                            if (blank($get('attendance_place'))) {
                                                $set('attendance_place', $state);
                                            }
                                        }),

                                    Forms\Components\TextInput::make('city')
                                        ->label('Kota')
                                        ->required(fn (Forms\Get $get) => !in_array($get('claim_type'), ['Tol / Parkir', 'Tol', 'Parkir']))
                                        ->default(fn (Forms\Get $get) => $get('../../city'))
                                        ->placeholder(fn (Forms\Get $get) => $get('../../city') ?: 'Contoh: Purwokerto')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                            if ($state && blank($get('../../city'))) {
                                                $set('../../city', $state);
                                            }
                                        }),
                                ]),

                                Forms\Components\Grid::make([
                                    'default' => 1,
                                    'sm' => 2,
                                ])->schema([
                                    Forms\Components\TextInput::make('brand')
                                        ->label('Brand')
                                        ->default(fn (Forms\Get $get) => $get('../../brand'))
                                        ->placeholder(fn (Forms\Get $get) => $get('../../brand') ?: 'Contoh: REALME'),

                                    Forms\Components\TextInput::make('reffnote')
                                        ->label('Reffnote')
                                        ->default(fn (Forms\Get $get) => $get('../../reffnote'))
                                        ->placeholder(fn (Forms\Get $get) => $get('../../reffnote') ?: 'Contoh: REALME PURWOKERTO'),
                                ]),

                                // SECTION OPSIONAL REKENING TUJUAN LAIN (Khusus Karangan Bunga)
                                Forms\Components\Section::make('Rekening Tujuan Transfer Toko Bunga / Vendor (Opsional)')
                                    ->description('Jika pembayaran karangan bunga langsung ditransfer oleh Finance ke toko bunga tujuan, silakan isi data rekening berikut:')
                                    ->icon('heroicon-o-banknotes')
                                    ->visible(fn (Forms\Get $get) => $get('claim_type') === 'Entertain' && $get('entertain_subtype') === 'Karangan Bunga')
                                    ->collapsed(false)
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\TextInput::make('custom_bank_account_name')
                                            ->label('Nama Rekening Tujuan (a/n Toko Bunga / Vendor)')
                                            ->placeholder('Contoh: Toko Bunga Florist Lestari / Budi')
                                            ->helperText('Opsional. Kosongkan jika ingin ditransfer ke rekening pribadi pemohon.'),

                                        Forms\Components\TextInput::make('custom_bank_name')
                                            ->label('Nama Bank Tujuan')
                                            ->placeholder('Contoh: BCA / Mandiri / BRI')
                                            ->datalist(['BCA', 'MANDIRI', 'BRI', 'BNI', 'BSI', 'CIMB NIAGA', 'PERMATA', 'DANAMON', 'JAGO', 'SEABANK']),

                                        Forms\Components\TextInput::make('custom_bank_account_number')
                                            ->label('Nomor Rekening Tujuan')
                                            ->placeholder('Contoh: 1234567890'),
                                    ])->columns(3)
                                      ->columnSpanFull(),

                                // SECTION DAFTAR HADIR (Khusus ASM / RGM: Pertemuan Makan & Ulang Tahun)
                                Forms\Components\Section::make('Daftar Hadir Pertemuan / Kegiatan Entertain')
                                    ->description('Data Perihal, Tempat, dan Tanggal kegiatan otomatis diambil dari data di atas agar tidak perlu double input.')
                                    ->icon('heroicon-o-clipboard-document-check')
                                    ->visible(function (Forms\Get $get) {
                                        $user = auth()->user();
                                        // Role Sales tidak perlu daftar hadir
                                        if ($user && $user->isSales()) {
                                            return false;
                                        }
                                        // Hanya untuk jenis Entertain kategori Makan dan Ulang Tahun
                                        return $get('claim_type') === 'Entertain' 
                                            && in_array($get('entertain_subtype'), ['Makan', 'Ulang Tahun']);
                                    })
                                    ->collapsed(false)
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\Grid::make([
                                            'default' => 1,
                                            'md' => 12,
                                        ])->schema([
                                            Forms\Components\TextInput::make('attendance_purpose')
                                                ->label('Perihal / Agenda Kegiatan')
                                                ->default(fn (Forms\Get $get) => $get('purpose'))
                                                ->placeholder(fn (Forms\Get $get) => $get('purpose') ?: 'Otomatis mengikuti Keperluan di atas')
                                                ->helperText('Otomatis mengambil dari data Keperluan di atas (tidak perlu double isi).')
                                                ->columnSpanFull(),

                                            Forms\Components\TextInput::make('attendance_place')
                                                ->label('Tempat / Lokasi')
                                                ->default(fn (Forms\Get $get) => $get('note'))
                                                ->placeholder(fn (Forms\Get $get) => $get('note') ?: 'Otomatis mengikuti Lokasi/Tempat di atas')
                                                ->helperText('Otomatis mengambil dari data Lokasi/Tempat di atas.')
                                                ->columnSpan(['default' => 1, 'md' => 7]),

                                            Forms\Components\DatePicker::make('attendance_date')
                                                ->label('Tanggal Kegiatan')
                                                ->default(fn (Forms\Get $get) => $get('receipt_date') ?? now())
                                                ->native(false)
                                                ->closeOnDateSelection()
                                                ->displayFormat('d/m/Y')
                                                ->helperText('Otomatis mengikuti Tanggal Nota di atas.')
                                                ->columnSpan(['default' => 1, 'md' => 5]),
                                        ]),

                                        Forms\Components\Textarea::make('attendance_notes')
                                            ->label('Catatan Kegiatan / Agenda Pertemuan (Opsional)')
                                            ->rows(2)
                                            ->columnSpanFull(),

                                        Forms\Components\Repeater::make('attendance_participants')
                                            ->label('Daftar Peserta Hadir (Tamu / Dealer / Tim)')
                                            ->schema([
                                                Forms\Components\Grid::make([
                                                    'default' => 1,
                                                    'md' => 12,
                                                ])->schema([
                                                    Forms\Components\TextInput::make('name')
                                                        ->label('Nama Peserta / Tamu')
                                                        ->required()
                                                        ->placeholder('Ketik nama (contoh: Budi / Toko Berkah Cell)')
                                                        ->datalist(fn () => \App\Models\Employee::pluck('name')->toArray())
                                                        ->columnSpan(['default' => 1, 'md' => 4]),

                                                    Forms\Components\TextInput::make('position')
                                                        ->label('Jabatan / Dealer / Toko')
                                                        ->placeholder('Contoh: Owner Dealer / TL / Sales')
                                                        ->columnSpan(['default' => 1, 'md' => 3]),

                                                    Forms\Components\Select::make('status')
                                                        ->label('Status')
                                                        ->options([
                                                            'HADIR' => 'Hadir / TTD',
                                                            'IZIN' => 'Izin',
                                                        ])
                                                        ->default('HADIR')
                                                        ->columnSpan(['default' => 1, 'md' => 2]),

                                                    Forms\Components\FileUpload::make('signature')
                                                        ->label('Upload TTD / Paraf (Opsional)')
                                                        ->image()
                                                        ->disk('public')
                                                        ->directory('attendance-signatures')
                                                        ->visibility('public')
                                                        ->columnSpan(['default' => 1, 'md' => 3]),
                                                ]),
                                            ])
                                            ->defaultItems(2)
                                            ->addActionLabel('+ Tambah Peserta Hadir')
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => !empty($state['name']) ? "{$state['name']}" . (!empty($state['position']) ? " ({$state['position']})" : '') : 'Peserta Hadir')
                                            ->columnSpanFull(),

                                        Forms\Components\FileUpload::make('attendance_photos')
                                            ->label('Foto Dokumentasi Pertemuan / Kegiatan')
                                            ->image()
                                            ->required()
                                            ->multiple()
                                            ->disk('public')
                                            ->directory('attendance-photos')
                                            ->visibility('public')
                                            ->columnSpanFull()
                                            ->helperText('Upload foto kegiatan/pertemuan untuk dilampirkan bersama lembar daftar hadir.'),

                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Rincian Transaksi (Multi-Item)')
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => !empty($state['claim_type']) ? ("{$state['claim_type']}" . (!empty($state['entertain_subtype']) && $state['claim_type'] === 'Entertain' ? " ({$state['entertain_subtype']})" : '') . (!empty($state['purpose']) ? " - {$state['purpose']}" : '') . (!empty($state['amount']) ? " - Rp " . number_format((float)$state['amount'], 0, ',', '.') : '')) : 'Item Transaksi')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Total Nominal Klaim (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->readOnly()
                            ->dehydrated()
                            ->helperText('Total dihitung otomatis dari seluruh rincian transaksi di atas.')
                            ->default(0)
                            ->columnSpan(2),
                    ]),

                Forms\Components\Section::make('Lampiran Bukti Nota / Kwitansi')
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
                            ->openable()
                            ->required()
                            ->downloadable()
                            ->columnSpanFull(),
                    ]),

                // Forms\Components\Section::make('Status Alur & _UID Finance')
                //     ->schema([
                //         Forms\Components\TextInput::make('_uid')
                //             ->label('_UID (Nomor ID Form External)')
                //             ->placeholder('Diisi setelah admin input ke form external')
                //             ->helperText('Setelah _UID terisi, pengajuan siap diproses pencairan oleh Finance.'),

                //         Forms\Components\Select::make('approval_status')
                //             ->label('Status Approval')
                //             ->options([
                //                 'DRAFT' => 'DRAFT',
                //                 'DIAJUKAN' => 'DIAJUKAN',
                //                 'ACC_ASM' => 'ACC_ASM',
                //                 'ACC_RGM' => 'ACC_RGM',
                //                 'ACC_PAK_JEJEN' => 'ACC_PAK_JEJEN',
                //                 'DISETUJUI' => 'DISETUJUI',
                //                 'DITOLAK' => 'DITOLAK',
                //             ])
                //             ->default('DRAFT')
                //             ->required(),

                //         Forms\Components\Select::make('disbursement_status')
                //             ->label('Status Pencairan')
                //             ->options([
                //                 'Belum Dicairkan' => 'Belum Dicairkan',
                //                 'Sudah Dicairkan' => 'Sudah Dicairkan',
                //             ])
                //             ->default('Belum Dicairkan')
                //             ->required(),
                //     ])->columns(3),
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
                    ->sortable(),
                Tables\Columns\TextColumn::make('claim_type_string')
                    ->label('Jenis')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('purpose_string')
                    ->label('Keperluan')
                    ->limit(35),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->weight('bold')
                    ->sortable()
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

                Tables\Filters\SelectFilter::make('entertain_subtype')
                    ->label('Kategori Entertain')
                    ->options([
                        'Makan' => 'Makan (Potong Plafon)',
                        'Lainnya' => 'Lainnya (Non Plafon)',
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
                Tables\Actions\Action::make('export_te_excel')
                    ->label('Export Rekapan Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(function (\Filament\Tables\Contracts\HasTable $livewire) {
                        $claims = $livewire->getFilteredTableQuery()
                            ->with(['employee.role', 'branch'])
                            ->orderBy('claim_date', 'asc')
                            ->get();

                        return app(\App\Services\ClaimExcelExportService::class)->exportEntertainRecapExcel($claims);
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                \App\Filament\Actions\ViewTransferProofAction::make(),

                // 1. Approval Atasan: ACC ASM
                Tables\Actions\Action::make('acc_asm')
                    ->label('ACC ASM')
                    ->icon('heroicon-o-check')
                    ->color('info')
                    ->visible(fn (Claim $record) => $record->approval_status === 'DIAJUKAN' && Claim::isAsmSupervisorOf(auth()->user(), $record))
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Klaim (ASM)')
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim ini untuk diteruskan ke RGM?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_ASM',
                            'approved_by_asm_id' => $user?->id,
                            'approved_by_asm_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan klaim disetujui ASM! Diteruskan ke RGM.')->info()->send();
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
                        Notification::make()->title('Pengajuan klaim ditolak ASM.')->warning()->send();
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
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim ini untuk diteruskan ke Admin untuk verifikasi?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_RGM',
                            'approved_by_rgm_id' => $user?->id,
                            'approved_by_rgm_at' => now(),
                        ]);
                        Notification::make()->title('Pengajuan klaim disetujui RGM! Diteruskan ke Admin untuk verifikasi.')->primary()->send();
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
                        Notification::make()->title('Pengajuan klaim ditolak RGM.')->warning()->send();
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
                    ->modalDescription('Pastikan seluruh kelengkapan nota dan data pengajuan telah sah. Klaim akan diteruskan ke Head of Sales (Pak Jejen) untuk persetujuan.')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'ACC_ADMIN',
                            'admin_approved_by_id' => $user?->id,
                            'admin_approved_at' => now(),
                        ]);
                        Notification::make()->title('Klaim telah diverifikasi Admin! Diteruskan ke Head of Sales (Pak Jejen).')->success()->send();
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
                        Notification::make()->title('Pengajuan klaim ditolak oleh Admin.')->warning()->send();
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
                    ->modalHeading('Konfirmasi Persetujuan Klaim (Head of Sales)')
                    ->modalDescription(function (Claim $record) {
                        $isTop = $record->isTopSelular();
                        $nextTarget = $isTop ? 'Manajer Keuangan (Thia Aisyah) - CV Top Selular' : 'Manager Finance (Yoga P. Hadi) - PT Media Selular Indonesia';
                        return "Apakah Anda menyetujui pengajuan klaim ini? Pengajuan akan diteruskan ke {$nextTarget}.";
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
                            ->label('Alasan Penolakan / Catatan Perbaikan')
                            ->placeholder('Tuliskan hal yang perlu diperbaiki...')
                            ->required(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Pengajuan klaim ditolak Pak Jejen.')->warning()->send();
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
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim ini untuk diteruskan ke Finance Coordinator (Bu Nurbaeti Riaseha)?')
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
                        Notification::make()->title('Klaim disetujui Pak Yoga! Diteruskan ke Bu Nurbaeti Riaseha (Finance Coordinator).')->success()->send();
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
                        Notification::make()->title('Klaim ditolak Pak Yoga.')->warning()->send();
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
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim ini untuk diteruskan ke antrean pencairan Finance PT Media Selular Indonesia?')
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
                        Notification::make()->title('Klaim disetujui Bu Nurbaeti! Diteruskan ke antrean pencairan Finance PT Media Selular Indonesia.')->success()->send();
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
                        Notification::make()->title('Klaim ditolak Bu Nurbaeti.')->warning()->send();
                    }),

                // 4.1.C Approval Manajer Keuangan (Thia Aisyah - CV Top Selular)
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
                    ->modalDescription('Apakah Anda menyetujui pengajuan klaim ini untuk diteruskan ke antrean pencairan Finance CV Top Selular?')
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
                        Notification::make()->title('Klaim disetujui Bu Thia! Diteruskan ke antrean pencairan Finance CV Top Selular.')->success()->send();
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
                        Notification::make()->title('Klaim ditolak Bu Thia.')->warning()->send();
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
                    ->modalDescription('Apakah data revisi sudah benar dan siap diajukan kembali ke Finance untuk pencairan?')
                    ->action(function (Claim $record) {
                        $user = auth()->user();
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'admin_approved_by_id' => $user?->id,
                            'admin_approved_at' => now(),
                        ]);
                        Notification::make()->title('Klaim hasil revisi berhasil dikirimkan kembali ke Finance!')->success()->send();
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
                    ->modalHeading('Ajukan Kembali Pengajuan Klaim')
                    ->modalDescription('Pastikan data dan foto nota telah diperbaiki sesuai catatan sebelum diajukan kembali.')
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

                // Download Actions
                Tables\Actions\Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn (Claim $record) => app(\App\Services\ClaimPdfExportService::class)->exportTransportEntertainSinglePdf($record)),

                Tables\Actions\Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),

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
            'index' => Pages\ListTransportEntertainClaims::route('/'),
            'create' => Pages\CreateTransportEntertainClaim::route('/create'),
            'edit' => Pages\EditTransportEntertainClaim::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceDocResource\Pages;
use App\Models\AttendanceDoc;
use App\Models\Branch;
use App\Models\Claim;
use App\Models\Employee;
use App\Services\AttendanceImageGeneratorService;
use App\Services\AttendanceWordExportService;
use App\Services\ClaimExcelExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceDocResource extends Resource
{
    protected static ?string $model = AttendanceDoc::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'DOKUMEN';

    protected static ?string $navigationLabel = 'Dokumen Daftar Hadir';

    protected static ?string $modelLabel = 'Dokumen Daftar Hadir';

    protected static ?string $pluralModelLabel = 'Dokumen Daftar Hadir';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isExecutiveApprover() || $user->isSales()) return false;
        return true;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isExecutiveApprover() || $user->isSales()) return false;
        return true;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isExecutiveApprover() || $user->isSales()) return false;
        return true;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isExecutiveApprover() || $user->isSales()) return false;
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user', 'employee.role', 'claim']);
        $user = auth()->user();

        // Admin & SuperAdmin can see ALL attendance docs submitted by anyone (ASM, RGM, etc.)
        if ($user && !$user->isAdmin() && !$user->isSuperAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                $empId = $user->getEffectiveEmployeeId();
                if ($empId) {
                    $q->orWhere('employee_id', $empId);
                }
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kegiatan & Kategori Acara')
                    ->description('Isi data agenda pertemuan/acara untuk pembuatan Dokumen Daftar Hadir dan pengajuan klaim entertain.')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Pemohon / Karyawan')
                            ->relationship('employee', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->name} ({$record->position_name})")
                            ->default(fn () => auth()->user()?->getEffectiveEmployeeId())
                            ->disabled(fn () => !auth()->user()?->isAdmin() && !auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(['default' => 1, 'sm' => 2]),

                        Forms\Components\Select::make('category')
                            ->label('Kategori Kegiatan Entertain')
                            ->options([
                                'Makan' => 'Makan Bersama (Jamuan Tim / Dealer - Plafon Bulanan)',
                                'Ulang Tahun' => 'Ulang Tahun (Kue Ultah / Traktiran - Non Plafon)',
                                'Meeting Koordinasi' => 'Meeting Koordinasi',
                                'Lainnya' => 'Lainnya',
                            ])
                            ->default('Makan')
                            ->required()
                            ->columnSpan(['default' => 1, 'sm' => 2])
                            ->helperText('Pilih Makan atau Ulang Tahun untuk diteruskan menjadi klaim entertain.'),

                        // 1. PERIHAL
                        Forms\Components\TextInput::make('purpose')
                            ->label('Perihal / Agenda Acara')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Jamuan Makan & Koordinasi Team Leader Purwokerto')
                            ->columnSpan(['default' => 1, 'sm' => 4]),

                        // 2. TGL
                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal Kegiatan')
                            ->default(now())
                            ->required()
                            ->columnSpan(['default' => 1, 'sm' => 2]),

                        // 3. TEMPAT
                        Forms\Components\TextInput::make('place')
                            ->label('Tempat / Lokasi Acara')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Resto Mang Engking Purwokerto')
                            ->columnSpan(['default' => 1, 'sm' => 2]),

                        // 4. ESTIMASI BIAYA
                        Forms\Components\TextInput::make('estimated_amount')
                            ->label('Estimasi Total Biaya / Nota (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->placeholder('Contoh: 350000')
                            ->helperText('Opsional: Cantumkan total nominal yang tertera pada nota/struk kasir. Admin akan memverifikasi dan menyesuaikan nominal ini.')
                            ->columnSpan(['default' => 1, 'sm' => 4]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Agenda (Opsional)')
                            ->rows(2)
                            ->placeholder('Catatan pembahasan atau keterangan kegiatan...')
                            ->columnSpanFull(),
                    ])->columns(['default' => 1, 'sm' => 4]),

                Forms\Components\Section::make('Daftar Peserta Hadir')
                    ->description('Tambahkan nama peserta pertemuan secara bebas (tidak wajib karyawan).')
                    ->schema([
                        Forms\Components\Repeater::make('participants')
                            ->relationship('participants')
                            ->label('Daftar Peserta')
                            ->schema([
                                Forms\Components\Grid::make(['default' => 1, 'sm' => 12])
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nama Peserta / Tamu')
                                            ->required()
                                            ->placeholder('Ketik nama (Contoh: Budi Santoso / Toko Berkah Cell)')
                                            ->datalist(fn () => Employee::pluck('name')->toArray())
                                            ->columnSpan(['default' => 1, 'sm' => 6]),

                                        Forms\Components\TextInput::make('position')
                                            ->label('Jabatan / Dealer / Instansi')
                                            ->placeholder('Contoh: Owner Dealer / Team Leader / Sales')
                                            ->columnSpan(['default' => 1, 'sm' => 4]),

                                        Forms\Components\Select::make('signature_info')
                                            ->label('Status Hadir')
                                            ->options([
                                                'HADIR' => 'Hadir / TTD',
                                                'IZIN' => 'Izin',
                                            ])
                                            ->default('HADIR')
                                            ->columnSpan(['default' => 1, 'sm' => 2]),
                                    ]),
                            ])
                            ->defaultItems(2)
                            ->addActionLabel('+ Tambah Peserta Hadir')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Foto Dokumentasi & Bukti Pembayaran')
                    ->description('Upload foto kegiatan dan foto nota/struk pembayaran untuk diverifikasi Admin.')
                    ->schema([
                        Forms\Components\FileUpload::make('photos')
                            ->label('Foto Dokumentasi Pertemuan / Kegiatan')
                            ->image()
                            ->multiple()
                            ->disk('public')
                            ->directory('attendance-photos')
                            ->visibility('public')
                            ->reorderable()
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull()
                            ->helperText('Foto saat acara pertemuan, jamuan makan bersama, atau penyerahan kue ultah.'),

                        Forms\Components\FileUpload::make('receipt_photos')
                            ->label('Foto Nota / Struk Kasir / Bukti Pembayaran')
                            ->image()
                            ->multiple()
                            ->disk('public')
                            ->directory('claim-photos')
                            ->visibility('public')
                            ->reorderable()
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull()
                            ->helperText('Upload foto struk kasir, bon, nota fisik, atau kwitansi pembayaran agar Admin dapat menyesuaikan dan memproses klaim resmi.'),
                    ]),

                Forms\Components\Section::make('Bagian Penyesuaian Admin')
                    ->description('Khusus Admin: Informasi status penyesuaian dokumen menjadi pengajuan klaim resmi.')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('admin_status')
                            ->label('Status Penyesuaian Dokumen')
                            ->options([
                                'MENUNGGU_PENYESUAIAN' => '🟡 Menunggu Penyesuaian Admin',
                                'SUDAH_DISESUAIKAN' => '🟢 Sudah Disesuaikan Menjadi Klaim',
                                'SELESAI' => '✅ Selesai / Disetujui',
                            ])
                            ->default('MENUNGGU_PENYESUAIAN')
                            ->required(),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Catatan Penyesuaian Admin')
                            ->placeholder('Catatan atau keterangan penyesuaian nominal/cabang...')
                            ->rows(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_employee.name')
                    ->label('Pemohon')
                    ->weight('bold')
                    ->description(fn (AttendanceDoc $record) => $record->effective_employee?->position_name ?? ($record->user?->name ?? '-'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Makan' => 'success',
                        'Ulang Tahun' => 'warning',
                        'Meeting Koordinasi' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('purpose')
                    ->label('Perihal / Agenda')
                    ->weight('medium')
                    ->searchable()
                    ->sortable()
                    ->limit(35),

                Tables\Columns\TextColumn::make('place')
                    ->label('Tempat')
                    ->searchable()
                    ->default('-')
                    ->limit(25),

                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Peserta')
                    ->counts('participants')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('estimated_amount')
                    ->label('Estimasi Biaya')
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Rp ' . number_format((float)$state, 0, ',', '.') : '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('admin_status')
                    ->label('Status Klaim')
                    ->badge()
                    ->state(function (AttendanceDoc $record) {
                        if ($record->claim_id && $record->claim) {
                            return 'Klaim: ' . ($record->claim->_uid ?: '#' . $record->claim->id);
                        }
                        return 'Menunggu Penyesuaian';
                    })
                    ->color(fn (AttendanceDoc $record) => $record->claim_id ? 'success' : 'warning'),
            ])
            ->defaultSort('date', 'desc')
            ->actions([
                // 1. Action: Sesuaikan & Buatkan Klaim (Hanya untuk Admin jika belum ada claim_id)
                Tables\Actions\Action::make('adjust_and_create_claim')
                    ->label('Sesuaikan & Buatkan Klaim')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (AttendanceDoc $record) => (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin()) && empty($record->claim_id))
                    ->modalHeading(fn (AttendanceDoc $record) => "Sesuaikan & Terbitkan Klaim: {$record->purpose}")
                    ->modalDescription('Admin menyesuaikan data cabang, brand, dan nominal riil sesuai nota fisik untuk diteruskan ke alur approval.')
                    ->modalWidth('2xl')
                    ->form(fn (AttendanceDoc $record) => [
                        Forms\Components\Placeholder::make('pemohon_info')
                            ->label('Pemohon')
                            ->content($record->effective_employee?->name . ' (' . ($record->effective_employee?->position_name ?? 'ASM/RGM') . ')'),

                        Forms\Components\Select::make('branch_id')
                            ->label('Cabang / Branch Pembukuan')
                            ->options(Branch::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->required()
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

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('brand')
                                ->label('Brand')
                                ->required()
                                ->placeholder('Contoh: REALME'),

                            Forms\Components\TextInput::make('reffnote')
                                ->label('Reffnote')
                                ->required()
                                ->placeholder('Contoh: REALME PURWOKERTO'),

                            Forms\Components\TextInput::make('city')
                                ->label('Kota')
                                ->required()
                                ->default($record->place),
                        ]),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\DatePicker::make('claim_date')
                                ->label('Tanggal Nota / Klaim')
                                ->default($record->date ?? now())
                                ->required(),

                            Forms\Components\Select::make('entertain_subtype')
                                ->label('Kategori Entertain')
                                ->options([
                                    'Makan' => 'Makan (Potong Plafon Bulanan)',
                                    'Lainnya' => 'Ulang Tahun / Lainnya (Non-Plafon)',
                                ])
                                ->default($record->category === 'Ulang Tahun' ? 'Lainnya' : 'Makan')
                                ->required(),
                        ]),

                        Forms\Components\TextInput::make('amount')
                            ->label('Nominal Riil Klaim yang Disetujui (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->default($record->estimated_amount > 0 ? $record->estimated_amount : null)
                            ->helperText('Periksa foto nota/struk pembayaran yang dilampirkan oleh ASM/RGM.'),

                        Forms\Components\TextInput::make('purpose')
                            ->label('Keperluan')
                            ->default($record->purpose)
                            ->required(),

                        Forms\Components\TextInput::make('note')
                            ->label('Lokasi / Tempat Acara')
                            ->default($record->place)
                            ->required(),
                    ])
                    ->action(function (AttendanceDoc $record, array $data) {
                        $gen = app(AttendanceImageGeneratorService::class);
                        $participantsArray = $record->participants->map(fn ($p) => [
                            'name' => $p->name,
                            'position' => $p->position,
                            'status' => $p->signature_info ?? 'HADIR',
                        ])->toArray();

                        $itemData = [
                            'claim_type' => 'Entertain',
                            'entertain_subtype' => $data['entertain_subtype'],
                            'receipt_date' => $data['claim_date'],
                            'purpose' => $data['purpose'],
                            'note' => $data['note'],
                            'city' => $data['city'],
                            'brand' => $data['brand'],
                            'reffnote' => $data['reffnote'],
                            'amount' => (float)$data['amount'],
                            'attendance_purpose' => $record->purpose,
                            'attendance_place' => $record->place,
                            'attendance_date' => $record->date ? $record->date->toDateString() : now()->toDateString(),
                            'attendance_notes' => $record->notes,
                            'attendance_participants' => $participantsArray,
                            'attendance_photos' => $record->photos ?? [],
                        ];

                        $img = $gen->generateForClaimItem($itemData, null, 0);
                        if ($img) {
                            $itemData['attendance_image'] = $img;
                        }

                        $claimPhotos = array_values(array_filter(array_merge(
                            (array)($record->receipt_photos ?? []),
                            (array)($record->photos ?? [])
                        )));

                        $claim = Claim::create([
                            'claim_category' => 'transport_entertain',
                            'claim_type' => 'Entertain',
                            'user_id' => $record->user_id,
                            'employee_id' => $record->employee_id,
                            'branch_id' => $data['branch_id'],
                            'brand' => $data['brand'],
                            'reffnote' => $data['reffnote'],
                            'city' => $data['city'],
                            'claim_date' => $data['claim_date'],
                            'purpose' => $data['purpose'],
                            'note' => $data['note'],
                            'amount' => (float)$data['amount'],
                            'approval_status' => 'ACC_ADMIN', // Admin verified directly! Ready for Head of Sales!
                            'disbursement_status' => 'Belum Dicairkan',
                            'photos' => $claimPhotos,
                            'items' => [$itemData],
                            'verified_by_admin' => auth()->id(),
                            'admin_approved_at' => now(),
                        ]);

                        $record->update([
                            'claim_id' => $claim->id,
                            'admin_status' => 'SUDAH_DISESUAIKAN',
                            'admin_notes' => "Disesuaikan & dibuatkan klaim #{$claim->id} oleh Admin " . auth()->user()->name . " pada " . now()->format('d/m/Y H:i'),
                        ]);

                        Notification::make()
                            ->title('Klaim Entertain Berhasil Diterbitkan!')
                            ->body("Data berhasil disesuaikan dengan nominal Rp " . number_format((float)$data['amount'], 0, ',', '.') . " dan langsung masuk antrean persetujuan Head of Sales.")
                            ->success()
                            ->send();
                    }),

                // 2. Action: Lihat Klaim Terkait (Jika sudah dibuatkan klaim)
                Tables\Actions\Action::make('view_claim')
                    ->label('Lihat Klaim')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->visible(fn (AttendanceDoc $record) => !empty($record->claim_id))
                    ->url(fn (AttendanceDoc $record) => $record->claim_id ? route('filament.admin.resources.transport-entertain-claims.edit', ['record' => $record->claim_id]) : null)
                    ->openUrlInNewTab(),

                // 3. Action: Unduh Excel Daftar Hadir (.xlsx)
                Tables\Actions\Action::make('download_excel')
                    ->label('Excel (.xlsx)')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->action(function (AttendanceDoc $record) {
                        if ($record->claim) {
                            return app(ClaimExcelExportService::class)->exportSingleClaim($record->claim);
                        }

                        // If not yet linked to claim, sync temporary claim or download word
                        $tempClaim = $record->claim ?? new Claim([
                            'claim_category' => 'transport_entertain',
                            'claim_type' => 'Entertain',
                            'user_id' => $record->user_id,
                            'employee_id' => $record->employee_id,
                            'claim_date' => $record->date ?? now(),
                            'purpose' => $record->purpose,
                            'note' => $record->place,
                            'amount' => $record->estimated_amount ?? 0,
                            'photos' => array_merge((array)($record->receipt_photos ?? []), (array)($record->photos ?? [])),
                            'items' => [[
                                'claim_type' => 'Entertain',
                                'entertain_subtype' => $record->category === 'Ulang Tahun' ? 'Lainnya' : 'Makan',
                                'receipt_date' => $record->date ? $record->date->toDateString() : now()->toDateString(),
                                'purpose' => $record->purpose,
                                'note' => $record->place,
                                'amount' => $record->estimated_amount ?? 0,
                                'attendance_purpose' => $record->purpose,
                                'attendance_place' => $record->place,
                                'attendance_date' => $record->date ? $record->date->toDateString() : now()->toDateString(),
                                'attendance_participants' => $record->participants->map(fn ($p) => ['name' => $p->name, 'position' => $p->position, 'status' => $p->signature_info ?? 'HADIR'])->toArray(),
                                'attendance_photos' => $record->photos ?? [],
                            ]],
                        ]);
                        $tempClaim->id = 0;
                        return app(ClaimExcelExportService::class)->exportSingleClaim($tempClaim);
                    }),

                // 4. Action: Unduh Word (.docx)
                Tables\Actions\Action::make('download_word')
                    ->label('Word (.docx)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->action(fn (AttendanceDoc $record) => app(AttendanceWordExportService::class)->export($record)),

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
            'index' => Pages\ListAttendanceDocs::route('/'),
            'create' => Pages\CreateAttendanceDoc::route('/create'),
            'edit' => Pages\EditAttendanceDoc::route('/{record}/edit'),
        ];
    }
}

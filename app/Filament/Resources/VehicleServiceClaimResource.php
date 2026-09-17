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

    protected static ?string $navigationLabel = 'Klaim Service Motor Sales';

    protected static ?string $modelLabel = 'Klaim Service Motor Sales';

    protected static ?string $pluralModelLabel = 'Klaim Service Motor Sales';

    protected static ?int $navigationSort = 5;

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
                    ->extraAttributes(['style' => 'background: #6dbbefff; border-radius: 12px; padding: 1.2rem;']),

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
                            ->required(),

                        Forms\Components\DatePicker::make('claim_date')
                            ->label('Tanggal Service')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('branch_id')
                            ->label('Branch / Cabang')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(3),

                Forms\Components\Section::make('Rincian Klaim Service Motor Sales')
                    ->description('Ketentuan: Kompensasi Service Motor Sales Operasional.')
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
                                    ->label('Nominal Biaya Service (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->placeholder('Contoh: 150000'),

                                Forms\Components\TextInput::make('purpose')
                                    ->label('Keterangan / Rincian Service')
                                    ->default('Ganti oli mesin, filter, dan servis berkala motor operasional')
                                    ->columnSpanFull()
                                    ->required(),
                            ]),
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
                    ->color(fn ($state) => $state ? 'success' : 'gray')
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
                Tables\Columns\TextColumn::make('vehicle_type')
                    ->label('Kendaraan')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('note')
                    ->label('Bengkel')
                    ->limit(25),
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
                    ->placeholder('-'),
                Tables\Columns\ImageColumn::make('transfer_proof_photo')
                    ->label('Bukti Transfer Finance')
                    ->disk('public')
                    ->height(40)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'DIAJUKAN' => 'warning',
                        'ACC_ASM' => 'info',
                        'ACC_RGM' => 'primary',
                        'ACC_PAK_JEJEN', 'DISETUJUI' => 'success',
                        'DITOLAK' => 'danger',
                        default => 'gray',
                    }),
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

                Tables\Actions\Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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

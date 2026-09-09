<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BbmBeritaAcaraResource\Pages;
use App\Models\Claim;
use App\Models\Employee;
use App\Services\BbmBeritaAcaraWordExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BbmBeritaAcaraResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'PENGAJUAN KLAIM';

    protected static ?string $modelLabel = 'Berita Acara BBM';

    protected static ?string $pluralModelLabel = 'Berita Acara BBM';

    protected static ?int $navigationSort = 3;

    /**
     * ASM, RGM, Sales, Admin, SuperAdmin dapat mengakses Berita Acara
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance()) return false;
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isAsm() || $user->isRgm() || $user->isSales();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('claim_category', 'ba_bbm')
            ->with(['employee.role', 'employee.positionModel', 'branch', 'claimPeriod.employee']);

        $user = auth()->user();
        // Hanya Super Admin yang dapat melihat semua data
        if ($user && !$user->isSuperAdmin()) {
            $empId = $user->getEffectiveEmployeeId();
            $query->where(function (Builder $q) use ($user, $empId) {
                $q->where('user_id', $user->id);
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
                Forms\Components\Hidden::make('user_id')->default(fn () => auth()->id()),
                Forms\Components\Hidden::make('claim_category')->default('ba_bbm'),

                // Section 0: _UID & STATUS di paling atas
                Forms\Components\Section::make('_UID EXTERNAL & STATUS PENGAJUAN')
                    ->description('Nomor referensi ID Form External dan status approval. Diisi setelah admin input ke sistem finance.')
                    ->icon('heroicon-o-identification')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('_uid')
                            ->label('_UID (ID Form External)')
                            ->placeholder('Contoh: UID-2026-09-001')
                            ->helperText('Diisi oleh admin setelah dokumen diinput ke sistem finance.'),

                        Forms\Components\Select::make('approval_status')
                            ->label('Status Approval')
                            ->options([
                                'DRAFT'         => 'DRAFT',
                                'DIAJUKAN'      => 'DIAJUKAN',
                                'ACC_ASM'       => 'ACC_ASM',
                                'ACC_RGM'       => 'ACC_RGM',
                                'ACC_PAK_JEJEN' => 'ACC_PAK_JEJEN',
                                'DISETUJUI'     => 'DISETUJUI',
                                'DITOLAK'       => 'DITOLAK',
                            ])
                            ->default('DRAFT')
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

                // Section 1: Identitas Pemohon & Dokumen
                Forms\Components\Section::make('Identitas Pemohon & Berita Acara')
                    ->description('Data pemohon dan identitas unit kerja. Berita Acara Ketidaksesuaian BBM PT. Media Selular Indonesia.')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Pemohon / Karyawan')
                            ->relationship('employee', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->name} ({$record->position_name})")
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\DatePicker::make('claim_date')
                            ->label('Tanggal Berita Acara')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('ba_phone')
                            ->label('No. Telp / HP Pemohon')
                            ->placeholder('+62 8xx-xxxx-xxxx')
                            ->required(),

                        Forms\Components\TextInput::make('ba_division')
                            ->label('Divisi / Unit Kerja')
                            ->default('Distribusi Sales - TECNO')
                            ->placeholder('Contoh: Distribusi Sales - TECNO / Realme')
                            ->required(),
                    ])->columns(2),

                // Section 2: Detail Ketidaksesuaian / Over Budget
                Forms\Components\Section::make('Detail Ketidaksesuaian & Nominal Over Budget')
                    ->description('Masukkan nominal yang melebihi batas plafon BBM sehingga dapat diklaim melalui Berita Acara ini.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->schema([
                        Forms\Components\Select::make('vehicle_type')
                            ->label('Jenis Kendaraan')
                            ->options([
                                'Mobil' => 'Mobil',
                                'Motor' => 'Motor',
                            ])
                            ->default('Mobil')
                            ->required(),

                        Forms\Components\Select::make('fuel_type')
                            ->label('Jenis BBM')
                            ->options([
                                'Pertalite' => 'Pertalite',
                                'Pertamax' => 'Pertamax',
                                'Solar'    => 'Solar',
                            ])
                            ->default('Pertalite'),

                        Forms\Components\TextInput::make('fuel_base_amount')
                            ->label('Total Pengeluaran BBM Aktual (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->live(onBlur: true)
                            ->helperText('Jumlah total biaya BBM yang sebenarnya dikeluarkan bulan ini.')
                            ->required(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Nominal Over Budget yang Diklaim (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->helperText('Selisih yang melebihi plafon BBM. Inilah yang diajukan melalui Berita Acara ini.'),

                        Forms\Components\Textarea::make('purpose')
                            ->label('Alasan / Keterangan Ketidaksesuaian')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Jelaskan mengapa biaya BBM melebihi batas plafon, contoh: kunjungan ke outlet luar kota, situasi darurat, dll.')
                            ->required(),
                    ])->columns(2),

                // Section 3: Pihak yang Menyetujui
                Forms\Components\Section::make('Pihak yang Menyetujui (Approver)')
                    ->icon('heroicon-o-check-badge')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('ba_approver_1')
                                    ->label('Approver 1')
                                    ->default('Agus Supangat')
                                    ->helperText('Status: Approve by WA')
                                    ->required(),

                                Forms\Components\TextInput::make('ba_approver_2')
                                    ->label('Approver 2')
                                    ->default('Alb. Maria Adi Nugroho')
                                    ->required(),

                                Forms\Components\TextInput::make('ba_approver_3')
                                    ->label('Approver 3')
                                    ->default('Yoga Prima Hadi')
                                    ->required(),
                            ]),
                    ]),

                // Section 4: Lampiran Dokumen
                Forms\Components\Section::make('Lampiran Bukti Dokumen')
                    ->description('Unggah screenshot persetujuan WhatsApp dan foto odometer sebelum & sesudah.')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        Forms\Components\FileUpload::make('ba_wa_proof_photo')
                            ->label('Foto Screenshot Persetujuan WhatsApp (WA Approval)')
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
                            ->columnSpanFull(),

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
                                    ->downloadable(),

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
                                    ->downloadable(),
                            ]),

                        Forms\Components\FileUpload::make('photos')
                            ->label('Foto Bukti Transaksi / Lampiran Tambahan (Opsional)')
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

                Tables\Columns\TextColumn::make('ba_division')
                    ->label('Divisi')
                    ->default('-')
                    ->limit(20),

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
            ->actions([
                \App\Filament\Actions\ViewTransferProofAction::make(),

                Tables\Actions\Action::make('download_ba_word')
                    ->label('Download BA')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn (Claim $record) => app(BbmBeritaAcaraWordExportService::class)->export($record)),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBbmBeritaAcaras::route('/'),
            'create' => Pages\CreateBbmBeritaAcara::route('/create'),
            'edit' => Pages\EditBbmBeritaAcara::route('/{record}/edit'),
        ];
    }
}

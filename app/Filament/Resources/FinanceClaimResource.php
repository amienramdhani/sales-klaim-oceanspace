<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinanceClaimResource\Pages;
use App\Models\Claim;
use App\Services\ClaimExcelExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinanceClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'PENGAJUAN KLAIM';

    protected static ?string $modelLabel = 'Klaim yang Harus Nota Balik';

    protected static ?string $pluralModelLabel = 'Klaim yang Harus Nota Balik';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Klaim yang Harus Nota Balik';
    }

    public static function getNavigationGroup(): ?string
    {
        if (auth()->user()?->isFinance()) {
            return 'MENU FINANCE';
        }
        return 'PENGAJUAN KLAIM';
    }

    /**
     * Finance, Admin, SuperAdmin dapat mengakses menu Klaim yang Harus Nota Balik
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isFinance();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['employee.role', 'employee.positionModel', 'branch', 'claimPeriod.employee', 'financeApprovedBy'])
            ->where(function (Builder $q) {
                $q->where('claim_category', 'bbm')
                  ->orWhere('claim_category', 'perdin')
                  ->orWhere('is_perdin', true)
                  ->orWhere('nota_balik_submitted', true);
            });

        $user = auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // Finance: Tampilkan data nota balik yang sudah diajukan atau sudah dicairkan
        if ($user->isFinance()) {
            return $query->where(function (Builder $q) {
                $q->where('approval_status', '!=', 'DRAFT')
                  ->orWhere('disbursement_status', 'Sudah Dicairkan');
            });
        }

        if ($user->isAdmin()) {
            return $query->visibleToUser($user);
        }

        if (!$user->isSuperAdmin()) {
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
            ->disabled()
            ->schema([
                Forms\Components\Section::make('Informasi Pencairan & Bukti Transfer Finance')
                    ->description('Finance mentransfer dana BBM bulanan ke ASM/RGM setelah memeriksa Nota Balik / bukti BBM bulan sebelumnya')
                    ->schema([
                        Forms\Components\TextInput::make('_uid')
                            ->label('_UID (ID Form External Finance)')
                            ->required()
                            ->placeholder('Contoh: UID-2026-08-001'),

                        Forms\Components\Select::make('disbursement_status')
                            ->label('Status Pencairan')
                            ->options([
                                'Belum Dicairkan' => 'Belum Dicairkan',
                                'Sudah Dicairkan' => 'Sudah Dicairkan',
                            ])
                            ->default('Sudah Dicairkan')
                            ->required(),

                        Forms\Components\FileUpload::make('transfer_proof_photo')
                            ->label('Foto Bukti Transfer Pembayaran Dana BBM Bulan Berjalan')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('transfer-proofs')
                            ->visibility('public')
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull()
                            ->helperText('Wajib diunggah oleh bagian Finance sebagai bukti sah pencairan dana BBM bulan berjalan.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('_uid')
                    ->label('_UID')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->placeholder('Belum Ada _UID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('claim_date')
                    ->label('Tanggal Nota Balik')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Pemohon (ASM / RGM)')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->state(fn (Claim $record) => $record->effective_employee?->name ?? '-')
                    ->description(fn (Claim $record) => $record->effective_employee?->position_name ?? '-'),

                // Budget BBM User (diambil dari profil karyawan)
                Tables\Columns\TextColumn::make('budget_bbm_user')
                    ->label('Budget BBM User')
                    ->money('IDR')
                    ->weight('bold')
                    ->color('primary')
                    ->state(fn (Claim $record) => (float)($record->effective_employee?->bbm_budget ?? 0)),

                // Total Realisasi Nota Balik
                Tables\Columns\TextColumn::make('amount')
                    ->label('Realisasi Nota Balik')
                    ->money('IDR')
                    ->weight('bold')
                    ->color('warning')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total Realisasi')),

                // Status Sisa / Pengembalian
                Tables\Columns\TextColumn::make('sisa_status')
                    ->label('Sisa / Pengembalian')
                    ->badge()
                    ->state(function (Claim $record) {
                        $budget = (float)($record->effective_employee?->bbm_budget ?? 0);
                        $used = (float)$record->amount;
                        $sisa = $budget - $used;

                        if ($budget <= 0) return 'Tanpa Plafon';
                        if ($sisa < 0) return 'Over Budget (+Rp ' . number_format(abs($sisa), 0, ',', '.') . ')';
                        if ($sisa == 0) return 'Pas Sesuai Budget';

                        if (!empty($record->return_transfer_proof)) {
                            return 'Sudah Transfer Balik (Rp ' . number_format((float)$record->return_transfer_amount, 0, ',', '.') . ')';
                        }
                        return 'Wajib Balik: Rp ' . number_format($sisa, 0, ',', '.');
                    })
                    ->color(function (string $state) {
                        if (str_contains($state, 'Sudah Transfer Balik')) return 'success';
                        if (str_contains($state, 'Wajib Balik')) return 'danger';
                        if (str_contains($state, 'Over')) return 'warning';
                        return 'gray';
                    }),

                Tables\Columns\TextColumn::make('disbursement_status')
                    ->label('Pencairan Bulan Ini')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sudah Dicairkan' ? 'success' : 'danger'),

                // Indikator apakah sudah ada bukti BBM bulan sebelumnya
                Tables\Columns\IconColumn::make('has_prev_month_proof')
                    ->label('Bukti BBM Bln Lalu')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (Claim $record) => $record->bbm_photo_before || $record->bbm_photo_combined || $record->bbm_photo_after
                        ? 'Bukti penggunaan BBM bulan sebelumnya sudah lengkap'
                        : 'Belum ada bukti BBM bulan sebelumnya')
                    ->state(fn (Claim $record) => !empty($record->bbm_photo_before) || !empty($record->bbm_photo_combined) || !empty($record->bbm_photo_after)),

                Tables\Columns\TextColumn::make('financeApprovedBy.name')
                    ->label('Diproses Oleh')
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),
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

                Tables\Filters\SelectFilter::make('disbursement_status')
                    ->label('Status Pencairan')
                    ->options([
                        'Belum Dicairkan' => 'Belum Dicairkan',
                        'Sudah Dicairkan' => 'Sudah Dicairkan',
                    ]),
            ])
            ->actions([
                // 1. Tombol Lihat Semua Berkas Pengajuan Klaim (Galeri Nota, Struk, Kwitansi, BBM)
                \App\Filament\Actions\ViewClaimFilesAction::make(),

                // 2. Tombol Detail Form Klaim (Read-Only)
                Tables\Actions\ViewAction::make()
                    ->label('Detail')
                    ->color('gray'),

                // 3. Tombol Transfer Dana & Kirim Bukti Transfer
                Tables\Actions\Action::make('transfer_dana')
                    ->label('Transfer Dana')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn (Claim $record) => $record->disbursement_status !== 'Sudah Dicairkan' && $record->approval_status !== 'DITOLAK')
                    ->modalHeading(fn (Claim $record) => "Kirim Bukti Transfer: " . ($record->effective_employee?->name ?? 'Pemohon'))
                    ->modalDescription(function (Claim $record) {
                        $budget = (float)($record->claim_category === 'bbm' && (float)($record->effective_employee?->bbm_budget ?? 0) > 0 
                            ? $record->effective_employee?->bbm_budget 
                            : $record->amount);
                        return "Nominal yang dicairkan untuk " . ($record->effective_employee?->name ?? 'Pemohon') . " adalah sebesar Rp " . number_format($budget, 0, ',', '.') . ". Silakan unggah foto / bukti transfer bank:";
                    })
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
                            ->helperText('Wajib unggah foto / screenshot bukti transfer pencairan dana.'),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $user = auth()->user();
                        $record->update([
                            'disbursement_status' => 'Sudah Dicairkan',
                            'disbursed_at' => now(),
                            'transfer_proof_photo' => $data['transfer_proof_photo'],
                            'finance_approved_by_id' => $user?->id,
                            'finance_approved_at' => now(),
                            'approval_status' => in_array($record->approval_status, ['DRAFT', 'DIAJUKAN', 'SEDANG_DIREVISI']) ? 'DISETUJUI' : $record->approval_status,
                        ]);
                        Notification::make()->title('Dana berhasil dicairkan dan Bukti Transfer tersimpan!')->success()->send();
                    }),

                // 4. Tombol Tolak Pengajuan dengan Catatan
                Tables\Actions\Action::make('tolak_pengajuan')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Claim $record) => $record->approval_status !== 'DITOLAK')
                    ->modalHeading(fn (Claim $record) => "Tolak Pengajuan Nota Balik: " . ($record->effective_employee?->name ?? 'Pemohon'))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Catatan / Alasan Penolakan Finance')
                            ->placeholder('Tuliskan alasan mengapa pengajuan nota balik ini ditolak...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                            'disbursement_status' => 'Belum Dicairkan',
                        ]);
                        Notification::make()->title('Pengajuan klaim berhasil ditolak dengan catatan.')->warning()->send();
                    }),

                // 5. Lihat Bukti Transfer Pencairan (Finance -> User)
                \App\Filament\Actions\ViewTransferProofAction::make(),

                // 6. Lihat Bukti Transfer Pengembalian Sisa (User -> Finance)
                \App\Filament\Actions\ViewReturnTransferProofAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinanceClaims::route('/'),
        ];
    }
}

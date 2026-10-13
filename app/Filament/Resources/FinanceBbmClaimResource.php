<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinanceBbmClaimResource\Pages;
use App\Models\Claim;
use App\Services\BbmClaimWordExportService;
use App\Services\ClaimPdfExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinanceBbmClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'MENU FINANCE';

    protected static ?string $modelLabel = 'Nota Balik BBM';

    protected static ?string $pluralModelLabel = 'Nota Balik BBM';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Nota Balik BBM';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = cache()->remember('badge_finance_bbm', 10, fn () => Claim::pendingFinanceBbmDisbursement()->count());
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return now()->day > 10 ? 'danger' : 'warning';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isExecutiveApprover()) return false;
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
                  ->orWhere('claim_type', 'like', '%BBM%');
            })
            ->latest('claim_date');

        $user = auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isFinance()) {
            return $query->visibleToFinance($user);
        }

        if ($user->isAdmin()) {
            return $query->visibleToUser($user);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return ClaimResource::form($form);
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

                Tables\Columns\TextColumn::make('badan_usaha')
                    ->label('Badan Usaha')
                    ->badge()
                    ->color('secondary')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('claim_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_employee.name')
                    ->label('Pemohon')
                    ->description(fn (Claim $record) => ($record->effective_employee?->position_name ?? 'Staff') . ' - ' . ($record->branch?->name ?? $record->region ?? '-'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fuel_type')
                    ->label('BBM & Liter')
                    ->formatStateUsing(fn ($state, Claim $record) => ($state ?: 'BBM') . ($record->fuel_liters > 0 ? " ({$record->fuel_liters} L)" : ''))
                    ->badge()
                    ->color('info')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal Klaim')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Status Approval & Tracking')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Claim $record): string => $record->approval_stage_label)
                    ->color(fn (string $state): string => match ($state) {
                        'DISETUJUI' => 'success',
                        'DITOLAK_FINANCE', 'DITOLAK' => 'danger',
                        'ACC_ASM', 'ACC_RGM', 'ACC_ADMIN' => 'primary',
                        'ACC_PAK_JEJEN', 'WAITING_YOGA', 'WAITING_THIA', 'WAITING_NURBAITI' => 'indigo',
                        'SEDANG_DIREVISI', 'DIAJUKAN' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (Claim $record) => $record->approval_journey_summary),

                Tables\Columns\TextColumn::make('disbursement_status')
                    ->label('Status Pencairan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Sudah Dicairkan' => 'success',
                        'Belum Dicairkan' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('financeApprovedBy.name')
                    ->label('Dicairkan Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('disbursed_at')
                    ->label('Waktu Transfer')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
                    ->options(['2025' => '2025', '2026' => '2026', '2027' => '2027'])
                    ->query(fn (Builder $query, array $data) => !empty($data['value']) ? $query->whereYear('claim_date', $data['value']) : $query),

                Tables\Filters\SelectFilter::make('disbursement_status')
                    ->label('Status Pencairan')
                    ->options([
                        'Belum Dicairkan' => 'Belum Dicairkan',
                        'Sudah Dicairkan' => 'Sudah Dicairkan',
                    ]),
            ])
            ->actions([
                // Lihat Detail Pengajuan
                Tables\Actions\ViewAction::make()
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('primary'),

                // Lihat Berkas Nota / Odometer
                \App\Filament\Actions\ViewClaimFilesAction::make(),

                // Bukti Transfer (jika sudah cair)
                \App\Filament\Actions\ViewTransferProofAction::make(),

                // Unduh Dokumen Khusus BBM: Word & PDF
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('export_bbm_word')
                        ->label('Unduh Word (.docx)')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->action(fn (Claim $record) => app(BbmClaimWordExportService::class)->export($record)),

                    Tables\Actions\Action::make('export_bbm_pdf')
                        ->label('Unduh PDF (.pdf)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(fn (Claim $record) => app(ClaimPdfExportService::class)->exportBbmSinglePdf($record)),
                ])
                ->label('Dokumen')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray'),

                // Cairkan Dana BBM & Upload Bukti Transfer
                Tables\Actions\Action::make('transfer_dana')
                    ->label('Cairkan BBM & Upload Bukti')
                    ->icon('heroicon-o-credit-card')
                    ->button()
                    ->color('success')
                    ->visible(fn (Claim $record) => $record->disbursement_status !== 'Sudah Dicairkan' && $record->approval_status === 'DISETUJUI')
                    ->modalHeading(fn (Claim $record) => "Pencairan Dana BBM [" . ($record->_uid ?: 'Tanpa UID') . "] - " . ($record->effective_employee?->name ?? 'Pemohon'))
                    ->modalDescription(function (Claim $record) {
                        $uidText = $record->_uid ? "UID Pengajuan: {$record->_uid}" : "UID: (Belum Ada UID)";
                        $budget = (float)($record->effective_employee?->bbm_budget > 0 
                            ? $record->effective_employee->bbm_budget 
                            : $record->amount);
                        return "{$uidText} | Nominal pencairan dana BBM untuk " . ($record->effective_employee?->name ?? 'Pemohon') . " adalah Rp " . number_format($budget, 0, ',', '.') . ". Harap unggah foto bukti transfer bank berikut:";
                    })
                    ->form([
                        Forms\Components\Placeholder::make('display_uid')
                            ->label('UID Pengajuan Awal')
                            ->content(fn (Claim $record) => new \Illuminate\Support\HtmlString(
                                '<span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-bold bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-300 border border-sky-300 dark:border-sky-700 tracking-wide">' . 
                                e($record->_uid ?: '(Tanpa UID Pengajuan)') . 
                                '</span>'
                            )),

                        Forms\Components\FileUpload::make('transfer_proof_photo')
                            ->label('Foto Bukti Transfer Bank')
                            ->image()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1920')
                            ->disk('public')
                            ->directory('transfer-proofs')
                            ->visibility('public')
                            ->required()
                            ->openable(),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'disbursement_status' => 'Sudah Dicairkan',
                            'disbursed_at' => now(),
                            'transfer_proof_photo' => $data['transfer_proof_photo'] ?? null,
                            'finance_approved_by_id' => auth()->id(),
                            'finance_approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Dana BBM Berhasil Dicairkan')
                            ->body('Bukti transfer telah tersimpan dan dapat dilihat oleh pemohon.')
                            ->success()
                            ->send();
                    }),

                // Tolak Pengajuan
                Tables\Actions\Action::make('tolak_pengajuan')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->button()
                    ->color('danger')
                    ->visible(fn (Claim $record) => $record->disbursement_status !== 'Sudah Dicairkan' && $record->approval_status === 'DISETUJUI')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan ke Admin')
                            ->placeholder('Contoh: Foto nota bbm buram atau odometer tidak sesuai, mohon diperbaiki.')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK_FINANCE',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        Notification::make()
                            ->title('Nota Balik BBM Dikembalikan ke Admin')
                            ->body('Admin dapat merevisi data dan mengirim ulang ke antrean Finance.')
                            ->danger()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinanceBbmClaims::route('/'),
        ];
    }
}

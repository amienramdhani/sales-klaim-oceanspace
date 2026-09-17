<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinanceNonBbmClaimResource\Pages;
use App\Models\Claim;
use App\Services\ClaimExcelExportService;
use App\Services\ClaimPdfExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinanceNonBbmClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'MENU FINANCE';

    protected static ?string $modelLabel = 'Pencairan Langsung (Non-BBM)';

    protected static ?string $pluralModelLabel = 'Pencairan Langsung (Non-BBM)';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Pencairan Langsung (Non-BBM)';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Claim::pendingFinanceNonBbmDisbursement()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isJejen()) return false;
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
                $q->whereNull('claim_category')
                  ->orWhere('claim_category', '!=', 'bbm');
            })
            ->where(function (Builder $q) {
                $q->whereNull('claim_type')
                  ->orWhere('claim_type', 'not like', '%BBM%');
            })
            ->latest('claim_date');

        $user = auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isFinance()) {
            return $query->where(function (Builder $q) {
                $q->whereIn('approval_status', ['DISETUJUI', 'DITOLAK_FINANCE'])
                  ->orWhere('disbursement_status', 'Sudah Dicairkan');
            });
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
                Tables\Columns\TextColumn::make('claim_category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'transport_entertain' => 'Transport/Entertain',
                        'perdin' => 'Perjalanan Dinas',
                        'service', 'service_motor' => 'Service Motor',
                        default => strtoupper($state ?? 'KLAIM'),
                    })
                    ->color(fn ($state) => match ($state) {
                        'transport_entertain' => 'success',
                        'perdin' => 'warning',
                        default => 'info',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('claim_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_employee.name')
                    ->label('Pemohon')
                    ->description(fn (Claim $record) => ($record->effective_employee?->position_name ?? 'Staff') . ' - ' . ($record->branch?->name ?? $record->region ?? '-'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purpose_string')
                    ->label('Keperluan / Tujuan')
                    ->limit(35)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal Klaim')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Status Approval')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'DISETUJUI' => 'Siap Dicairkan',
                        'DITOLAK_FINANCE' => 'Ditolak Finance (Revisi Admin)',
                        'DITOLAK' => 'Ditolak',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'DISETUJUI' => 'success',
                        'DITOLAK_FINANCE', 'DITOLAK' => 'danger',
                        default => 'gray',
                    }),

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
                Tables\Filters\SelectFilter::make('claim_category')
                    ->label('Kategori')
                    ->options([
                        'transport_entertain' => 'Klaim Transport & Entertain',
                        'perdin' => 'Perjalanan Dinas',
                        'service' => 'Service Motor',
                    ]),

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
                // Lihat Berkas Nota / Kwitansi
                \App\Filament\Actions\ViewClaimFilesAction::make(),

                // Detail
                Tables\Actions\ViewAction::make()
                    ->label('Detail')
                    ->color('gray'),

                // Bukti Transfer (jika sudah cair)
                \App\Filament\Actions\ViewTransferProofAction::make(),

                // Unduh Dokumen Khusus Non-BBM: Excel & PDF
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('export_excel')
                        ->label('Unduh Excel (.xlsx)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),

                    Tables\Actions\Action::make('export_pdf')
                        ->label('Unduh PDF (.pdf)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(fn (Claim $record) => app(ClaimPdfExportService::class)->exportTransportEntertainSinglePdf($record)),
                ])
                ->label('Dokumen')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray'),

                // Cairkan Dana Langsung & Upload Bukti Transfer
                Tables\Actions\Action::make('transfer_dana')
                    ->label('Cairkan Dana & Upload Bukti')
                    ->icon('heroicon-o-credit-card')
                    ->button()
                    ->color('success')
                    ->visible(fn (Claim $record) => $record->disbursement_status !== 'Sudah Dicairkan' && $record->approval_status === 'DISETUJUI')
                    ->modalHeading(fn (Claim $record) => "Pencairan Dana: " . ($record->effective_employee?->name ?? 'Pemohon'))
                    ->modalDescription(fn (Claim $record) => "Nominal pencairan untuk " . ($record->effective_employee?->name ?? 'Pemohon') . " adalah Rp " . number_format($record->amount, 0, ',', '.') . ". Harap unggah foto bukti transfer bank berikut:")
                    ->form([
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

                        Forms\Components\TextInput::make('_uid')
                            ->label('_UID Finance (Opsional)')
                            ->placeholder('Nomor voucher internal finance'),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'disbursement_status' => 'Sudah Dicairkan',
                            'disbursed_at' => now(),
                            'transfer_proof_photo' => $data['transfer_proof_photo'] ?? null,
                            '_uid' => $data['_uid'] ?? $record->_uid,
                            'finance_approved_by_id' => auth()->id(),
                            'finance_approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Dana Klaim Berhasil Dicairkan')
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
                            ->placeholder('Contoh: Kwitansi tidak jelas atau nominal tidak cocok, mohon direvisi.')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK_FINANCE',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        Notification::make()
                            ->title('Pengajuan Dikembalikan ke Admin')
                            ->body('Admin dapat merevisi data dan mengirim ulang ke antrean Finance.')
                            ->danger()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinanceNonBbmClaims::route('/'),
        ];
    }
}

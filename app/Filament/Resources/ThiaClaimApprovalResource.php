<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ThiaClaimApprovalResource\Pages;
use App\Models\Claim;
use App\Services\BbmClaimWordExportService;
use App\Services\ClaimExcelExportService;
use App\Services\ClaimPdfExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ThiaClaimApprovalResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'PERSETUJUAN KLAIM';

    protected static ?string $modelLabel = 'Persetujuan Klaim (Manajer Keuangan)';

    protected static ?string $pluralModelLabel = 'Persetujuan Klaim (Manajer Keuangan)';

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return 'Persetujuan Klaim (Manajer Keuangan)';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user || (!$user->isThia() && !$user->isSuperAdmin())) {
            return null;
        }
        $count = cache()->remember('badge_thia_approval', 10, fn () => Claim::pendingThiaApproval()->count());
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->isSuperAdmin() || $user->isThia();
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
        return parent::getEloquentQuery()
            ->with(['employee.role', 'employee.positionModel', 'branch', 'claimPeriod.employee'])
            ->latest('claim_date');
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
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('claim_category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bbm' => 'BBM',
                        'transport_entertain' => 'Transport/Entertain',
                        'perdin' => 'Perdin',
                        'service', 'service_motor' => 'Service Motor',
                        default => strtoupper($state ?? 'KLAIM'),
                    })
                    ->color(fn ($state) => match ($state) {
                        'bbm' => 'info',
                        'transport_entertain' => 'success',
                        'perdin' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('claim_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_employee.name')
                    ->label('Pemohon')
                    ->description(fn (Claim $record) => \Illuminate\Support\Str::limit(
                        ($record->effective_employee?->position_name ?? 'Staff') . ' - ' . ($record->branch?->name ?? $record->region ?? '-'),
                        40
                    ))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purpose_string')
                    ->label('Keperluan / Tujuan')
                    ->limit(35)
                    ->tooltip(fn ($state) => $state)
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Status Approval')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'WAITING_THIA' => 'Menunggu ACC Bu Thia',
                        'DISETUJUI' => 'Disetujui (Siap Dicairkan Finance CV Top Selular)',
                        'DITOLAK' => 'Ditolak',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'WAITING_THIA' => 'warning',
                        'DISETUJUI' => 'success',
                        'DITOLAK' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('claim_category')
                    ->label('Kategori Klaim')
                    ->options([
                        'bbm' => 'Nota Balik BBM',
                        'transport_entertain' => 'Klaim Transport & Entertain',
                        'perdin' => 'Perjalanan Dinas',
                        'service' => 'Service Motor',
                    ]),
            ])
            ->actions([
                \App\Filament\Actions\ViewClaimFilesAction::make(),

                Tables\Actions\Action::make('acc_thia')
                    ->label('ACC')
                    ->button()
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Claim $record) => $record->approval_status === 'WAITING_THIA')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Manajer Keuangan (Thia Aisyah)')
                    ->modalDescription(fn (Claim $record) => "Apakah Anda menyetujui klaim senilai Rp " . number_format($record->amount, 0, ',', '.') . " untuk " . ($record->effective_employee?->name ?? 'Pemohon') . "? Status akan disetujui penuh dan langsung diteruskan ke Finance CV Top Selular untuk pencairan dana.")
                    ->form([
                        Forms\Components\Textarea::make('approval_note')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->placeholder('Tambahkan catatan atau arahan persetujuan jika ada...')
                            ->rows(2),
                    ])
                    ->modalSubmitActionLabel('Ya, Konfirmasi & Setujui')
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'approved_by_thia_id' => auth()->id(),
                            'approved_by_thia_at' => now(),
                            'thia_approval_note' => $data['approval_note'] ?? null,
                            'approval_note' => $data['approval_note'] ?? $record->approval_note,
                        ]);

                        Notification::make()
                            ->title('Klaim Berhasil Disetujui Manajer Keuangan')
                            ->body('Status berubah menjadi DISETUJUI dan langsung diteruskan ke antrean Finance CV Top Selular untuk pencairan dana.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject_thia')
                    ->label('Tolak')
                    ->button()
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Claim $record) => $record->approval_status === 'WAITING_THIA')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        Notification::make()
                            ->title('Klaim Ditolak oleh Manajer Keuangan')
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('export_bbm_pdf')
                        ->label('PDF BBM (.pdf)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->visible(fn (Claim $record) => $record->claim_category === 'bbm' || str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM'))
                        ->action(fn (Claim $record) => app(ClaimPdfExportService::class)->exportBbmSinglePdf($record)),

                    Tables\Actions\Action::make('export_service_pdf')
                        ->label('PDF Service (.pdf)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->visible(fn (Claim $record) => in_array($record->claim_category, ['service', 'service_motor']))
                        ->action(fn (Claim $record) => app(ClaimPdfExportService::class)->exportVehicleServiceSinglePdf($record)),

                    Tables\Actions\Action::make('export_perdin_pdf')
                        ->label('PDF Perdin (.pdf)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->visible(fn (Claim $record) => $record->claim_category === 'perdin' || $record->is_perdin)
                        ->action(fn (Claim $record) => app(ClaimPdfExportService::class)->exportPerdinSinglePdf($record)),

                    Tables\Actions\Action::make('export_pdf')
                        ->label('PDF (.pdf)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->visible(fn (Claim $record) => $record->claim_category !== 'bbm' && !str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM') && !$record->is_perdin && !in_array($record->claim_category, ['service', 'service_motor']))
                        ->action(fn (Claim $record) => app(ClaimPdfExportService::class)->exportTransportEntertainSinglePdf($record)),

                    Tables\Actions\Action::make('export_excel')
                        ->label('Excel (.xlsx)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),
                ])->label('Aksi')->icon('heroicon-o-arrow-down-tray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_acc_thia')
                    ->label('Konfirmasi ACC Terpilih')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Seluruh Klaim Terpilih (Manajer Keuangan)')
                    ->modalDescription('Semua klaim terpilih akan disetujui penuh oleh Manajer Keuangan (Thia Aisyah) dan diteruskan ke antrean pencairan Finance CV Top Selular.')
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->approval_status === 'WAITING_THIA') {
                                $record->update([
                                    'approval_status' => 'DISETUJUI',
                                    'approved_by_thia_id' => auth()->id(),
                                    'approved_by_thia_at' => now(),
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title("{$count} Klaim Berhasil Disetujui Manajer Keuangan")
                            ->body('Seluruh klaim terpilih berstatus DISETUJUI dan masuk antrean pencairan Finance CV Top Selular.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListThiaClaimApprovals::route('/'),
        ];
    }
}

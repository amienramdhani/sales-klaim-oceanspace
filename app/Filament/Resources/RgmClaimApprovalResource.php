<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RgmClaimApprovalResource\Pages;
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

class RgmClaimApprovalResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'PERSETUJUAN KLAIM';

    protected static ?string $modelLabel = 'Persetujuan Klaim (RGM)';

    protected static ?string $pluralModelLabel = 'Persetujuan Klaim (RGM)';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Persetujuan Klaim (RGM)';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user || (!$user->isRgm() && !$user->isSuperAdmin())) {
            return null;
        }
        $count = Claim::pendingRgmApproval($user)->count();
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
        return $user->isSuperAdmin() || $user->isRgm();
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
        $user = auth()->user();
        $empId = $user?->getEffectiveEmployeeId();

        $query = parent::getEloquentQuery()
            ->with(['employee.role', 'employee.positionModel', 'branch', 'claimPeriod.employee'])
            ->latest('claim_date');

        if ($user && !$user->isSuperAdmin()) {
            $query->where('user_id', '!=', $user->id);
            if ($empId) {
                $query->where('employee_id', '!=', $empId);
            }
            $query->where(function (Builder $q) use ($user, $empId) {
                if ($empId) {
                    $q->whereHas('employee', fn ($eq) => $eq->where('supervisor_id', $empId))
                      ->orWhereHas('employee.supervisor', fn ($sq) => $sq->where('supervisor_id', $empId));
                }
                $regions = $user->getRegionList();
                if (!empty($regions)) {
                    $q->orWhereHas('employee', fn ($eq) => $eq->whereIn('region', $regions));
                }
            });
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
                    ->description(fn (Claim $record) => ($record->effective_employee?->position_name ?? 'Staff') . ' - ' . ($record->branch?->name ?? $record->region ?? '-'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purpose_string')
                    ->label('Keperluan / Tujuan')
                    ->limit(35)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Status Approval')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'DIAJUKAN' => 'Menunggu Persetujuan',
                        'ACC_ASM' => 'ACC ASM (Menunggu RGM)',
                        'ACC_RGM' => 'ACC RGM (Menunggu Pak Jejen)',
                        'ACC_PAK_JEJEN' => 'ACC Pak Jejen (Menunggu Admin)',
                        'DISETUJUI' => 'Disetujui Admin (Siap Cair)',
                        'DITOLAK_FINANCE' => 'Ditolak Finance (Perlu Revisi)',
                        'DITOLAK' => 'Ditolak',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'DIAJUKAN' => 'warning',
                        'ACC_ASM' => 'info',
                        'ACC_RGM' => 'primary',
                        'ACC_PAK_JEJEN' => 'secondary',
                        'DISETUJUI' => 'success',
                        'DITOLAK', 'DITOLAK_FINANCE' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan Pada')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('claim_category')
                    ->label('Kategori Klaim')
                    ->options([
                        'bbm' => 'Klaim BBM',
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
            ])
            ->actions([
                // Lihat Berkas
                \App\Filament\Actions\ViewClaimFilesAction::make(),

                // Detail
                Tables\Actions\ViewAction::make()
                    ->label('Detail')
                    ->color('gray'),

                // ACC RGM Action
                Tables\Actions\Action::make('acc_rgm')
                    ->label('ACC RGM')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->visible(function (Claim $record) {
                        $isAsmApplicant = $record->effective_employee?->isAsm() ?? false;
                        if ($isAsmApplicant) {
                            return $record->approval_status === 'DIAJUKAN';
                        }
                        return $record->approval_status === 'ACC_ASM';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan RGM')
                    ->modalDescription(fn (Claim $record) => "Apakah Anda yakin menyetujui klaim senilai Rp " . number_format($record->amount, 0, ',', '.') . " dari " . ($record->effective_employee?->name ?? 'Pemohon') . "? Pengajuan akan diteruskan ke Head of Sales (Pak Jejen).")
                    ->action(function (Claim $record) {
                        $record->update([
                            'approval_status' => 'ACC_RGM',
                            'approved_by_rgm_id' => auth()->id(),
                            'approved_by_rgm_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Klaim Berhasil Disetujui RGM')
                            ->body('Status berubah menjadi ACC RGM dan diteruskan ke Head of Sales.')
                            ->success()
                            ->send();
                    }),

                // Tolak RGM Action
                Tables\Actions\Action::make('reject_rgm')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        $isAsmApplicant = $record->effective_employee?->isAsm() ?? false;
                        if ($isAsmApplicant) {
                            return $record->approval_status === 'DIAJUKAN';
                        }
                        return $record->approval_status === 'ACC_ASM';
                    })
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
                            ->title('Klaim Ditolak oleh RGM')
                            ->danger()
                            ->send();
                    }),

                // Download Dokumen
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('export_bbm_word')
                        ->label('Word (.docx)')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->visible(fn (Claim $record) => $record->claim_category === 'bbm' || str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM'))
                        ->action(fn (Claim $record) => app(BbmClaimWordExportService::class)->export($record)),

                    Tables\Actions\Action::make('export_bbm_pdf')
                        ->label('PDF (.pdf)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->visible(fn (Claim $record) => $record->claim_category === 'bbm' || str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM'))
                        ->action(fn (Claim $record) => app(ClaimPdfExportService::class)->exportBbmSinglePdf($record)),

                    Tables\Actions\Action::make('export_excel')
                        ->label('Excel (.xlsx)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn (Claim $record) => $record->claim_category !== 'bbm' && !str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM'))
                        ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),

                    Tables\Actions\Action::make('export_pdf')
                        ->label('PDF (.pdf)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->visible(fn (Claim $record) => $record->claim_category !== 'bbm' && !str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM'))
                        ->action(fn (Claim $record) => app(ClaimPdfExportService::class)->exportTransportEntertainSinglePdf($record)),
                ])
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_acc_rgm')
                    ->label('ACC RGM Terpilih')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('ACC Seluruh Klaim Terpilih')
                    ->modalDescription('Semua klaim yang menunggu persetujuan RGM akan disetujui dan diteruskan ke Head of Sales (Pak Jejen).')
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            $isAsm = $record->effective_employee?->isAsm() ?? false;
                            $canApprove = ($isAsm && $record->approval_status === 'DIAJUKAN') || (!$isAsm && $record->approval_status === 'ACC_ASM');

                            if ($canApprove) {
                                $record->update([
                                    'approval_status' => 'ACC_RGM',
                                    'approved_by_rgm_id' => auth()->id(),
                                    'approved_by_rgm_at' => now(),
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title("{$count} Klaim Berhasil Disetujui RGM")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRgmClaimApprovals::route('/'),
        ];
    }
}

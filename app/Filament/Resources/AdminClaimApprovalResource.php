<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminClaimApprovalResource\Pages;
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

class AdminClaimApprovalResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'PERSETUJUAN KLAIM';

    protected static ?string $modelLabel = 'Verifikasi Klaim (Admin)';

    protected static ?string $pluralModelLabel = 'Verifikasi Klaim (Admin)';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return 'Verifikasi Klaim (Admin)';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin())) {
            return null;
        }
        $count = Claim::pendingAdminVerification($user)->count();
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
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()
            ->with(['employee.role', 'employee.positionModel', 'branch', 'claimPeriod.employee'])
            ->latest('claim_date');

        if ($user && !$user->isSuperAdmin()) {
            $managedRegions = $user->getManagedRegionsList();
            $query->where(function (Builder $q) use ($user, $managedRegions) {
                $q->where('user_id', $user->id);
                if (!empty($managedRegions)) {
                    $q->orWhere(function (Builder $sub) use ($managedRegions) {
                        foreach ($managedRegions as $region) {
                            $sub->orWhereRaw('UPPER(claims.region) LIKE ?', ['%' . strtoupper($region) . '%'])
                                ->orWhereRaw('UPPER(claims.homebase) LIKE ?', ['%' . strtoupper($region) . '%'])
                                ->orWhereRaw('UPPER(claims.city) LIKE ?', ['%' . strtoupper($region) . '%']);
                        }
                        $sub->orWhereHas('employee', function (Builder $eq) use ($managedRegions) {
                            foreach ($managedRegions as $region) {
                                $eq->orWhereRaw('UPPER(employees.region) LIKE ?', ['%' . strtoupper($region) . '%'])
                                   ->orWhereRaw('UPPER(employees.homebase) LIKE ?', ['%' . strtoupper($region) . '%']);
                            }
                        });
                    });
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

                Tables\Columns\TextColumn::make('rejection_reason')
                    ->label('Catatan Revisi Finance')
                    ->visible(fn ($livewire) => $livewire->activeTab === 'revisi_finance')
                    ->wrap()
                    ->color('danger'),

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

                // Edit (untuk revisi nota / amount jika ditolak finance)
                Tables\Actions\EditAction::make()
                    ->label('Edit / Revisi')
                    ->color('warning'),

                // Verifikasi & Setujui
                Tables\Actions\Action::make('admin_verify_approve')
                    ->label('Verifikasi & Setujui')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Claim $record) => in_array($record->approval_status, ['ACC_PAK_JEJEN', 'DITOLAK_FINANCE']))
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi & Setujui Klaim')
                    ->modalDescription(fn (Claim $record) => "Klaim senilai Rp " . number_format($record->amount, 0, ',', '.') . " untuk " . ($record->effective_employee?->name ?? 'Pemohon') . " akan disetujui dan langsung dikirimkan ke Finance untuk proses pencairan.")
                    ->action(function (Claim $record) {
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'admin_approved_by_id' => auth()->id(),
                            'admin_approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Klaim Berhasil Diverifikasi & Disetujui Admin')
                            ->body('Status berubah menjadi DISETUJUI dan langsung masuk ke antrean Finance.')
                            ->success()
                            ->send();
                    }),

                // Kirim Ulang ke Finance (Khusus setelah revisi penolakan finance)
                Tables\Actions\Action::make('admin_resubmit_to_finance')
                    ->label('Kirim Ulang ke Finance')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (Claim $record) => $record->approval_status === 'DITOLAK_FINANCE')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Ulang Revisi Klaim ke Finance')
                    ->modalDescription('Apakah perbaikan klaim sudah selesai? Klaim akan langsung dikirimkan kembali ke antrean Finance tanpa perlu meminta persetujuan ulang atasan.')
                    ->action(function (Claim $record) {
                        $record->update([
                            'approval_status' => 'DISETUJUI',
                            'admin_approved_by_id' => auth()->id(),
                            'admin_approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Klaim Berhasil Dikirim Ulang ke Finance')
                            ->body('Klaim telah diperbarui dan masuk kembali ke antrean pencairan Finance.')
                            ->success()
                            ->send();
                    }),

                // Tolak Action
                Tables\Actions\Action::make('reject_admin')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Claim $record) => in_array($record->approval_status, ['ACC_PAK_JEJEN', 'DITOLAK_FINANCE']))
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
                            ->title('Klaim Ditolak oleh Admin')
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
                Tables\Actions\BulkAction::make('bulk_admin_verify')
                    ->label('Verifikasi & Setujui Terpilih')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi Seluruh Klaim Terpilih')
                    ->modalDescription('Semua klaim yang menunggu verifikasi Admin akan disetujui dan dikirim ke Finance untuk pencairan.')
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if (in_array($record->approval_status, ['ACC_PAK_JEJEN', 'DITOLAK_FINANCE'])) {
                                $record->update([
                                    'approval_status' => 'DISETUJUI',
                                    'admin_approved_by_id' => auth()->id(),
                                    'admin_approved_at' => now(),
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title("{$count} Klaim Berhasil Diverifikasi & Disetujui Admin")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminClaimApprovals::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JejenClaimApprovalResource\Pages;
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

class JejenClaimApprovalResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'PERSETUJUAN KLAIM';

    protected static ?string $modelLabel = 'Persetujuan Klaim (Head of Sales)';

    protected static ?string $pluralModelLabel = 'Persetujuan Klaim (Head of Sales)';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Persetujuan Klaim (Head of Sales)';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user || (!$user->isJejen() && !$user->isSuperAdmin())) {
            return null;
        }
        $count = cache()->remember('badge_jejen_approval', 10, fn () => Claim::pendingJejenApproval()->count());
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
        return $user->isSuperAdmin() || $user->isJejen();
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
                        'DIAJUKAN' => 'Menunggu Persetujuan',
                        'ACC_ASM' => 'ACC ASM (Menunggu RGM)',
                        'ACC_RGM' => 'ACC RGM (Menunggu Admin)',
                        'ACC_ADMIN' => 'ACC Admin (Menunggu Pak Jejen)',
                        'ACC_PAK_JEJEN', 'DISETUJUI' => 'Disetujui Pak Jejen (Siap Cair)',
                        'DITOLAK_FINANCE' => 'Ditolak Finance (Perlu Revisi)',
                        'DITOLAK' => 'Ditolak',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'DIAJUKAN' => 'warning',
                        'ACC_ASM' => 'info',
                        'ACC_RGM' => 'primary',
                        'ACC_ADMIN' => 'secondary',
                        'ACC_PAK_JEJEN', 'DISETUJUI' => 'success',
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
                        'bbm' => 'Nota Balik BBM',
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

                // Tombol ACC di Baris Tabel (Menyetujui klaim yang sudah diverifikasi Admin)
                Tables\Actions\Action::make('acc_jejen')
                    ->label('ACC')
                    ->button()
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Claim $record) => $record->approval_status === 'ACC_ADMIN')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Head of Sales')
                    ->modalDescription(function (Claim $record) {
                        $isTop = $record->isTopSelular();
                        $nextTarget = $isTop ? 'Manajer Keuangan (Thia Aisyah) - CV Top Selular' : 'Manager Finance (Yoga P. Hadi) - PT Media Selular Indonesia';
                        return "Apakah Anda yakin menyetujui klaim senilai Rp " . number_format($record->amount, 0, ',', '.') . " untuk " . ($record->effective_employee?->name ?? 'Pemohon') . "? Pengajuan akan diteruskan ke {$nextTarget}.";
                    })
                    ->form([
                        Forms\Components\Textarea::make('approval_note')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->placeholder('Tambahkan catatan atau arahan persetujuan jika ada...')
                            ->rows(2),
                    ])
                    ->modalSubmitActionLabel('Ya, Konfirmasi & Setujui')
                    ->action(function (Claim $record, array $data) {
                        $isTop = $record->isTopSelular();

                        if ($isTop) {
                            $nextStatus = 'WAITING_THIA';
                            $msg = 'Klaim berhasil disetujui Head of Sales dan diteruskan ke Bu Thia Aisyah (Manajer Keuangan CV Top Selular).';
                        } else {
                            $nextStatus = 'WAITING_YOGA';
                            $msg = 'Klaim berhasil disetujui Head of Sales dan diteruskan ke Pak Yoga P. Hadi (Manager Finance PT Media Selular Indonesia).';
                        }

                        $record->update([
                            'approval_status' => $nextStatus,
                            'approved_by_jejen_id' => auth()->id(),
                            'approved_by_jejen_at' => now(),
                            'jejen_approval_note' => $data['approval_note'] ?? null,
                            'approval_note' => $data['approval_note'] ?? $record->approval_note,
                        ]);

                        Notification::make()
                            ->title('Klaim Berhasil Disetujui Head of Sales')
                            ->body($msg)
                            ->success()
                            ->send();
                    }),

                // Tombol Tolak di Baris Tabel
                Tables\Actions\Action::make('reject_jejen')
                    ->label('Tolak')
                    ->button()
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Claim $record) => $record->approval_status === 'ACC_ADMIN')
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
                            ->title('Klaim Ditolak oleh Head of Sales')
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

                    Tables\Actions\Action::make('export_attendance_word')
                        ->label('Daftar Hadir Word (.docx)')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('info')
                        ->visible(fn (Claim $record) => $record->hasAttendanceData())
                        ->action(function (Claim $record) {
                            $doc = $record->buildAttendanceDocInstance();
                            if (!$doc) {
                                Notification::make()->title('Data daftar hadir belum tersedia.')->warning()->send();
                                return null;
                            }
                            return app(\App\Services\AttendanceWordExportService::class)->export($doc);
                        }),
                ])
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_acc_jejen')
                    ->label('Konfirmasi ACC Terpilih')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Seluruh Klaim Terpilih (Head of Sales)')
                    ->modalDescription('Semua klaim terpilih yang diverifikasi Admin akan disetujui Head of Sales dan diteruskan ke tahapan persetujuan berikutnya (Manager Finance Pak Yoga untuk PT MSI atau Manajer Keuangan Bu Thia untuk CV Top Selular).')
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if (in_array($record->approval_status, ['ACC_ADMIN'])) {
                                $nextStatus = $record->isTopSelular() ? 'WAITING_THIA' : 'WAITING_YOGA';
                                $record->update([
                                    'approval_status' => $nextStatus,
                                    'approved_by_jejen_id' => auth()->id(),
                                    'approved_by_jejen_at' => now(),
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title("{$count} Klaim Berhasil Disetujui Head of Sales dan Diteruskan")
                            ->body('Klaim PT Media Selular Indonesia diteruskan ke Pak Yoga P. Hadi, dan klaim CV Top Selular diteruskan ke Bu Thia Aisyah.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJejenClaimApprovals::route('/'),
        ];
    }
}

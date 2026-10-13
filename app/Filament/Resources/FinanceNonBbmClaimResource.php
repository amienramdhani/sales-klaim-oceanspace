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
        $count = cache()->remember('badge_finance_non_bbm', 10, fn () => Claim::pendingFinanceNonBbmDisbursement()->count());
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
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
                    ->tooltip(fn ($state) => $state)
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

                Tables\Columns\TextColumn::make('nota_balik_status')
                    ->label('Status Nota Balik')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Disetujui Finance' => 'success',
                        'Ditolak Finance' => 'danger',
                        'Menunggu Verifikasi Finance' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (Claim $record) => $record->nota_balik_rejection_reason ? "Alasan: {$record->nota_balik_rejection_reason}" : null)
                    ->visible(fn () => true),

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
                // // Lihat Detail Pengajuan
                // Tables\Actions\ViewAction::make()
                //     ->label('Detail')
                //     ->icon('heroicon-o-eye')
                //     ->color('primary'),

                // Lihat Berkas Nota / Kwitansi
                \App\Filament\Actions\ViewClaimFilesAction::make(),

                // Bukti Transfer (jika sudah cair)
                \App\Filament\Actions\ViewTransferProofAction::make(),

                // Unduh Dokumen Khusus Non-BBM: Excel & PDF Sesuai Kategori
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('export_pdf')
                        ->label('Unduh PDF (.pdf)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(fn (Claim $record) => app(ClaimPdfExportService::class)->exportSingleClaimPdf($record)),

                    Tables\Actions\Action::make('export_excel')
                        ->label('Unduh Excel (.xlsx)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),

                    Tables\Actions\Action::make('print_kwitansi')
                        ->label('Kwitansi Makan Perdin')
                        ->icon('heroicon-o-receipt-percent')
                        ->color('info')
                        ->visible(fn (Claim $record) => ($record->is_perdin || $record->claim_category === 'perdin') && (float)$record->meal_allowance > 0)
                        ->modalHeading('Kwitansi Resmi Uang Makan Perjalanan Dinas')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalContent(fn (Claim $record) => view('filament.modals.kwitansi-perdin', ['record' => $record])),

                    Tables\Actions\Action::make('export_nota_balik_pdf')
                        ->label('PDF Nota Balik Perdin')
                        ->icon('heroicon-o-document-text')
                        ->color('warning')
                        ->visible(fn (Claim $record) => ($record->is_perdin || $record->claim_category === 'perdin') && ($record->nota_balik_submitted || (float)$record->actual_amount_total > 0))
                        ->action(fn (Claim $record) => app(ClaimPdfExportService::class)->exportNotaBalikSinglePdf($record)),
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
                    ->modalHeading(fn (Claim $record) => "Pencairan Dana [" . ($record->_uid ?: 'Tanpa UID') . "] - " . ($record->effective_employee?->name ?? 'Pemohon'))
                    ->modalDescription(function (Claim $record) {
                        $uidText = $record->_uid ? "UID Pengajuan: {$record->_uid}" : "UID: (Belum Ada UID)";
                        return "{$uidText} | Nominal pencairan untuk " . ($record->effective_employee?->name ?? 'Pemohon') . " adalah Rp " . number_format($record->amount, 0, ',', '.') . ". Harap unggah foto bukti transfer bank berikut:";
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

                // Finance ACC Nota Balik Perdin
                Tables\Actions\Action::make('approve_nota_balik_finance')
                    ->label('ACC Nota Balik')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        return ($record->is_perdin || $record->claim_category === 'perdin') &&
                               $record->nota_balik_submitted &&
                               $record->nota_balik_status !== 'Disetujui Finance';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi & ACC Nota Balik Perdin')
                    ->modalDescription(function (Claim $record) {
                        $desc = "Apakah seluruh nota fisik dan rincian realisasi (Total: Rp " . number_format((float)$record->actual_amount_total, 0, ',', '.') . ") telah diverifikasi sesuai?";
                        if ((float)$record->remaining_amount_calculated > 0) {
                            $desc .= " Terdapat sisa dana Rp " . number_format((float)$record->remaining_amount_calculated, 0, ',', '.') . " yang harus dikembalikan oleh pemohon.";
                        }
                        return $desc;
                    })
                    ->action(function (Claim $record) {
                        $record->update([
                            'nota_balik_status' => 'Disetujui Finance',
                            'nota_balik_finance_sent' => true,
                            'nota_balik_finance_sent_at' => now(),
                        ]);

                        $remaining = (float)$record->remaining_amount_calculated;
                        if ($remaining > 0) {
                            $remainingFmt = 'Rp ' . number_format($remaining, 0, ',', '.');
                            $recipient = $record->user ?? ($record->employee?->user);
                            if ($recipient) {
                                Notification::make()
                                    ->title('Verifikasi Selesai: Pengembalian Sisa Dana Perdin')
                                    ->body("Nota balik UID {$record->uid} telah disetujui Finance. Terdapat sisa dana sebesar {$remainingFmt} yang harus dikembalikan ke rekening kas Finance.")
                                    ->warning()
                                    ->sendToDatabase($recipient);
                            }
                        }

                        Notification::make()
                            ->title('Nota Balik Disetujui Finance')
                            ->body('Pertanggungjawaban Perdin telah diverifikasi lengkap dan sah.')
                            ->success()
                            ->send();
                    }),

                // Finance Tolak Nota Balik Perdin dengan Alasan (Item 16)
                Tables\Actions\Action::make('reject_nota_balik_finance')
                    ->label('Tolak Nota Balik')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        return ($record->is_perdin || $record->claim_category === 'perdin') &&
                               $record->nota_balik_submitted &&
                               $record->nota_balik_status !== 'Disetujui Finance';
                    })
                    ->form([
                        Forms\Components\Textarea::make('nota_balik_rejection_reason')
                            ->label('Alasan Penolakan Nota Balik')
                            ->placeholder('Contoh: Foto bukti struk fisik SPBU / tiket tidak terbaca atau nominal tidak sesuai...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'nota_balik_status' => 'Ditolak Finance',
                            'nota_balik_rejection_reason' => $data['nota_balik_rejection_reason'],
                            'nota_balik_submitted' => false,
                        ]);

                        Notification::make()
                            ->title('Nota Balik Ditolak Finance')
                            ->body('Catatan perbaikan telah dikirimkan ke pemohon untuk direvisi.')
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

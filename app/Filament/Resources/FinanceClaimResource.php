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

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return false;
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
            ->with(['employee.role', 'employee.positionModel', 'branch', 'claimPeriod.employee', 'financeApprovedBy']);

        $user = auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // Finance: Hanya menampilkan klaim yang sudah disetujui (DISETUJUI), sedang direvisi Admin (DITOLAK_FINANCE), atau sudah dicairkan
        if ($user->isFinance()) {
            $query->where(function (Builder $q) {
                $q->whereIn('approval_status', ['DISETUJUI', 'DITOLAK_FINANCE'])
                  ->orWhere('disbursement_status', 'Sudah Dicairkan');
            });

            if ($user->isFinanceTop() || $user->isFinanceCvcs()) {
                $query->where(function (Builder $q) {
                    $q->whereRaw("UPPER(COALESCE(badan_usaha, company, '')) LIKE '%TOP%'")
                      ->orWhereRaw("UPPER(COALESCE(badan_usaha, company, '')) LIKE '%COMPLETE%'")
                      ->orWhereRaw("UPPER(COALESCE(badan_usaha, company, '')) LIKE '%CV%'");
                });
            } elseif ($user->isFinanceMsi()) {
                $query->where(function (Builder $q) {
                    $q->whereRaw("UPPER(COALESCE(badan_usaha, company, '')) LIKE '%MEDIA SELULAR%'")
                      ->orWhereRaw("UPPER(COALESCE(badan_usaha, company, '')) LIKE '%MSI%'")
                      ->orWhere(function ($sub) {
                          $sub->whereRaw("UPPER(COALESCE(badan_usaha, company, '')) NOT LIKE '%TOP%'")
                              ->whereRaw("UPPER(COALESCE(badan_usaha, company, '')) NOT LIKE '%COMPLETE%'");
                      });
                });
            } elseif (!empty($user->badan_usaha)) {
                $bu = strtoupper(trim($user->badan_usaha));
                $query->where(function (Builder $q) use ($bu) {
                    $q->whereRaw('UPPER(badan_usaha) LIKE ?', ["%{$bu}%"])
                      ->orWhereRaw('UPPER(company) LIKE ?', ["%{$bu}%"]);
                });
            }

            return $query;
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
                    ->weight('bold')
                    ->copyable(),

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
                    ->state(fn (Claim $record) => (float)($record->effective_employee?->bbm_budget ?? 0))
                    ->visibleFrom('md'),

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
                    })
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('claim_category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn ($state, Claim $record) => match ($state) {
                        'bbm' => 'BBM',
                        'perdin' => 'Perdin',
                        'transport_entertain' => 'Entertain / Transport',
                        default => $record->claim_type_string ?: 'Klaim',
                    })
                    ->color(fn ($state) => match ($state) {
                        'bbm' => 'warning',
                        'perdin' => 'info',
                        'transport_entertain' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Status Approval')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DISETUJUI' => 'success',
                        'DITOLAK_FINANCE' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (Claim $record) => $record->approval_status === 'DITOLAK_FINANCE' && !empty($record->rejection_reason) ? "Catatan: {$record->rejection_reason}" : null),

                Tables\Columns\TextColumn::make('disbursement_status')
                    ->label('Pencairan Bulan Ini')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sudah Dicairkan' ? 'success' : 'danger')
                    ->visibleFrom('md'),

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
                    ->state(fn (Claim $record) => !empty($record->bbm_photo_before) || !empty($record->bbm_photo_combined) || !empty($record->bbm_photo_after))
                    ->visibleFrom('md'),

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

                // 3. Download Dokumen Khusus Klaim BBM (Word & PDF)
                Tables\Actions\Action::make('export_bbm_word')
                    ->label('Word')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (Claim $record) => $record->claim_category === 'bbm' || str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM'))
                    ->action(fn (Claim $record) => app(\App\Services\BbmClaimWordExportService::class)->export($record)),

                Tables\Actions\Action::make('export_bbm_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->visible(fn (Claim $record) => $record->claim_category === 'bbm' || str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM'))
                    ->action(fn (Claim $record) => app(\App\Services\ClaimPdfExportService::class)->exportBbmSinglePdf($record)),

                // 4. Download Dokumen Khusus Klaim Selain BBM (Excel & PDF)
                Tables\Actions\Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (Claim $record) => $record->claim_category !== 'bbm' && !str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM'))
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),

                Tables\Actions\Action::make('export_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->visible(fn (Claim $record) => $record->claim_category !== 'bbm' && !str_contains(strtoupper($record->claim_type_string ?? ''), 'BBM'))
                    ->action(fn (Claim $record) => app(\App\Services\ClaimPdfExportService::class)->exportTransportEntertainSinglePdf($record)),

                // 5. Tombol Transfer Dana & Kirim Bukti Transfer
                Tables\Actions\Action::make('transfer_dana')
                    ->label('Transfer Dana')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn (Claim $record) => $record->disbursement_status !== 'Sudah Dicairkan' && $record->approval_status === 'DISETUJUI')
                    ->modalHeading(fn (Claim $record) => "Kirim Bukti Transfer [" . ($record->_uid ?: 'Tanpa UID') . "] - " . ($record->effective_employee?->name ?? 'Pemohon'))
                    ->modalDescription(function (Claim $record) {
                        $uidText = $record->_uid ? "UID: {$record->_uid}" : "UID: (Belum Ada UID)";
                        $budget = (float)($record->claim_category === 'bbm' && (float)($record->effective_employee?->bbm_budget ?? 0) > 0 
                            ? $record->effective_employee?->bbm_budget 
                            : $record->amount);
                        return "{$uidText} | Nominal yang dicairkan untuk " . ($record->effective_employee?->name ?? 'Pemohon') . " adalah sebesar Rp " . number_format($budget, 0, ',', '.') . ". Silakan unggah foto / bukti transfer bank:";
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
                            'approval_status' => 'DISETUJUI',
                        ]);
                        Notification::make()->title('Dana berhasil dicairkan dan Bukti Transfer tersimpan!')->success()->send();
                    }),

                // 6. Tombol Tolak Pengajuan dengan Catatan (Meminta Revisi ke Admin)
                Tables\Actions\Action::make('tolak_pengajuan')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Claim $record) => $record->disbursement_status !== 'Sudah Dicairkan' && $record->approval_status !== 'DITOLAK_FINANCE')
                    ->modalHeading(fn (Claim $record) => "Tolak Pengajuan Klaim: " . ($record->effective_employee?->name ?? 'Pemohon'))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Catatan / Alasan Penolakan Finance (Untuk Direvisi Admin)')
                            ->placeholder('Tuliskan alasan mengapa pengajuan klaim ini ditolak dan perlu direvisi Admin...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Claim $record, array $data) {
                        $record->update([
                            'approval_status' => 'DITOLAK_FINANCE',
                            'rejection_reason' => $data['rejection_reason'],
                            'disbursement_status' => 'Belum Dicairkan',
                        ]);
                        Notification::make()->title('Pengajuan klaim berhasil ditolak. Admin dapat merevisi data klaim.')->warning()->send();
                    }),

                // 7. Lihat Bukti Transfer Pencairan (Finance -> User)
                \App\Filament\Actions\ViewTransferProofAction::make(),

                // 8. Lihat Bukti Transfer Pengembalian Sisa (User -> Finance)
                \App\Filament\Actions\ViewReturnTransferProofAction::make(),

                // 9. Finance: ACC Nota Balik Perdin
                Tables\Actions\Action::make('approve_nota_balik_finance')
                    ->label('ACC Nota Balik')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(function (Claim $record) {
                        return $record->is_perdin &&
                               $record->nota_balik_submitted &&
                               $record->nota_balik_status !== 'Disetujui Finance';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi & ACC Nota Balik Perjalanan Dinas')
                    ->modalDescription(function (Claim $record) {
                        $desc = "Apakah rincian realisasi pengeluaran dan seluruh bukti fisik telah dicek dan sesuai?";
                        if ((float)$record->remaining_amount_calculated > 0) {
                            $desc .= " Terdapat sisa dana Rp " . number_format((float)$record->remaining_amount_calculated, 0, ',', '.') . " yang akan dinotifikasikan ke pemohon untuk dikembalikan.";
                        }
                        return $desc;
                    })
                    ->action(function (Claim $record) {
                        $record->update([
                            'nota_balik_status' => 'Disetujui Finance',
                            'nota_balik_finance_sent' => true,
                            'nota_balik_finance_sent_at' => now(),
                        ]);

                        // Item 9: Berikan notifikasi jika terdapat sisa dana pengembalian
                        $remaining = (float)$record->remaining_amount_calculated;
                        if ($remaining > 0) {
                            $remainingFmt = 'Rp ' . number_format($remaining, 0, ',', '.');
                            $recipient = $record->user ?? ($record->employee?->user);
                            if ($recipient) {
                                Notification::make()
                                    ->title('Verifikasi Selesai: Pengembalian Sisa Dana Perdin')
                                    ->body("Nota balik UID {$record->uid} telah diverifikasi. Terdapat sisa dana sebesar {$remainingFmt} yang harus dikembalikan ke kas Finance.")
                                    ->warning()
                                    ->sendToDatabase($recipient);
                            }
                        }

                        Notification::make()
                            ->title('Nota Balik Disetujui Finance!')
                            ->body('Pertanggungjawaban Perdin telah lengkap dan disetujui.' . ($remaining > 0 ? ' Notifikasi pengembalian dana telah dikirim ke pemohon.' : ''))
                            ->success()
                            ->send();
                    }),

                // 10. Finance: Tolak Nota Balik dengan Catatan (Item 16)
                Tables\Actions\Action::make('reject_nota_balik_finance')
                    ->label('Tolak Nota Balik')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (Claim $record) {
                        return $record->is_perdin &&
                               $record->nota_balik_submitted &&
                               $record->nota_balik_status !== 'Disetujui Finance';
                    })
                    ->form([
                        Forms\Components\Textarea::make('nota_balik_rejection_reason')
                            ->label('Alasan Penolakan Nota Balik')
                            ->placeholder('Tuliskan alasan mengapa nota balik ditolak (contoh: foto struk SPBU buram, bukti transfer sisa dana belum terunggah, dll)...')
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
                            ->body('Catatan perbaikan telah dikirim ke pemohon.')
                            ->danger()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinanceClaims::route('/'),
        ];
    }
}

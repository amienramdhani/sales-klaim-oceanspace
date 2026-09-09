<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BbmReturnSaldoResource\Pages;
use App\Models\Employee;
use App\Models\Claim;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Resource untuk Finance melihat daftar pengembalian sisa dana BBM.
 * Setiap baris = 1 karyawan (ASM/RGM) + bulan tertentu.
 * Finance dapat melihat siapa yang sudah/belum mengembalikan sisa saldo.
 */
class BbmReturnSaldoResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'REKAPAN BIAYA';

    protected static ?string $modelLabel = 'Pengembalian Sisa Dana BBM';

    protected static ?string $pluralModelLabel = 'Pengembalian Sisa Dana BBM';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        // Hanya tampilkan karyawan yang memiliki peran ASM atau RGM
        $query = parent::getEloquentQuery()
            ->whereHas('role', fn ($q) => $q->whereIn('code', ['ASM', 'RGM', 'asm', 'rgm']))
            ->with(['role', 'positionModel', 'claims' => function ($q) {
                $q->where('claim_category', 'bbm')
                  ->orWhere('claim_type', 'like', '%BBM%')
                  ->orWhereNotNull('bbm_photo_before');
            }])
            ->where('status', 'Aktif');

        $user = auth()->user();
        if ($user && !$user->isSuperAdmin()) {
            $empId = $user->getEffectiveEmployeeId();
            if ($empId) {
                $query->where('id', $empId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        $currentMonth = now()->month;
        $currentYear  = now()->year;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('custom_id')
                    ->label('ID')
                    ->badge()
                    ->color('warning')
                    ->default('-'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Karyawan')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Employee $record) => $record->role?->name ?? $record->position ?? '-'),

                Tables\Columns\TextColumn::make('homebase')
                    ->label('Homebase')
                    ->default('-'),

                // Budget BBM bulan ini
                Tables\Columns\TextColumn::make('bbm_budget')
                    ->label('Budget BBM (Plafon)')
                    ->money('IDR')
                    ->color('primary')
                    ->weight('bold')
                    ->state(fn (Employee $record) => $record->bbm_budget),

                // Total dipakai bulan ini
                Tables\Columns\TextColumn::make('used_bbm_this_month')
                    ->label('Terpakai Bulan Ini')
                    ->money('IDR')
                    ->color('warning')
                    ->weight('semibold')
                    ->state(function (Employee $record) use ($currentMonth, $currentYear) {
                        return $record->getUsedBbmForPeriod($currentMonth, $currentYear);
                    }),

                // Sisa yang seharusnya dikembalikan
                Tables\Columns\TextColumn::make('sisa_harus_kembali')
                    ->label('Sisa yang Harus Dikembalikan')
                    ->money('IDR')
                    ->weight('bold')
                    ->color(fn (Employee $record) => self::getSisaForEmployee($record) > 0 ? 'danger' : 'success')
                    ->state(fn (Employee $record) => self::getSisaForEmployee($record)),

                // Status pengembalian
                Tables\Columns\TextColumn::make('return_status')
                    ->label('Status Pengembalian')
                    ->badge()
                    ->state(function (Employee $record) {
                        $klaim = $record->claims
                            ->where('claim_category', 'bbm')
                            ->filter(fn ($c) => !empty($c->return_transfer_proof))
                            ->first();

                        if ($klaim) {
                            return 'Sudah Dikembalikan';
                        }
                        $sisa = self::getSisaForEmployee($record);
                        return $sisa > 0 ? 'Belum Dikembalikan' : 'Tidak Ada Sisa';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Sudah Dikembalikan' => 'success',
                        'Belum Dikembalikan' => 'danger',
                        default              => 'gray',
                    }),

                // Tanggal pengembalian
                Tables\Columns\TextColumn::make('return_date')
                    ->label('Tanggal Kembali')
                    ->default('-')
                    ->state(function (Employee $record) {
                        $klaim = $record->claims
                            ->where('claim_category', 'bbm')
                            ->filter(fn ($c) => !empty($c->return_transfer_proof))
                            ->sortByDesc('return_transferred_at')
                            ->first();
                        return $klaim?->return_transferred_at
                            ? Carbon::parse($klaim->return_transferred_at)->format('d/m/Y')
                            : '-';
                    }),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Role / Jabatan')
                    ->relationship('role', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('return_filter')
                    ->label('Status Pengembalian')
                    ->options([
                        'has_return'    => 'Sudah Dikembalikan',
                        'no_return'     => 'Belum Dikembalikan / Ada Sisa',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (($data['value'] ?? null) === 'has_return') {
                            return $query->whereHas('claims', fn ($q) => $q->whereNotNull('return_transfer_proof'));
                        }
                        if (($data['value'] ?? null) === 'no_return') {
                            return $query->whereDoesntHave('claims', fn ($q) => $q->whereNotNull('return_transfer_proof'));
                        }
                        return $query;
                    }),
            ])
            ->headerActions([
                // Info header
                Tables\Actions\Action::make('info_header')
                    ->label('Cara Kerja')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->action(fn () => null)
                    ->tooltip('Sisa dana = Budget BBM - Total Terpakai. Jika ada sisa, karyawan wajib mengembalikan ke Finance.'),
            ])
            ->actions([
                // Lihat bukti transfer balik
                Tables\Actions\Action::make('view_return_proof')
                    ->label('Lihat Bukti Kembali')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (Employee $record) => $record->claims
                        ->where('claim_category', 'bbm')
                        ->filter(fn ($c) => !empty($c->return_transfer_proof))
                        ->isNotEmpty()
                    )
                    ->action(function (Employee $record) {
                        $klaim = $record->claims
                            ->where('claim_category', 'bbm')
                            ->filter(fn ($c) => !empty($c->return_transfer_proof))
                            ->sortByDesc('return_transferred_at')
                            ->first();

                        if ($klaim) {
                            Notification::make()
                                ->title("Bukti Pengembalian: {$record->name}")
                                ->body("Dikembalikan: Rp " . number_format($klaim->return_transfer_amount, 0, ',', '.') .
                                    "\nTanggal: " . ($klaim->return_transferred_at ? Carbon::parse($klaim->return_transferred_at)->format('d/m/Y') : '-') .
                                    "\nCatatan: " . ($klaim->return_transfer_notes ?? '-'))
                                ->success()
                                ->persistent()
                                ->send();
                        }
                    }),

                // Konfirmasi penerimaan oleh Finance
                Tables\Actions\Action::make('confirm_return')
                    ->label('Konfirmasi Diterima')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Konfirmasi bahwa Finance telah menerima pengembalian sisa dana BBM dari karyawan ini.')
                    ->visible(fn (Employee $record) => $record->claims
                        ->where('claim_category', 'bbm')
                        ->filter(fn ($c) => !empty($c->return_transfer_proof) && empty($c->finance_approved_by_id))
                        ->isNotEmpty()
                    )
                    ->action(function (Employee $record) {
                        $claim = $record->claims
                            ->where('claim_category', 'bbm')
                            ->filter(fn ($c) => !empty($c->return_transfer_proof) && empty($c->finance_approved_by_id))
                            ->first();
                        if ($claim) {
                            $claim->update([
                                'finance_approved_by_id' => auth()->id(),
                                'finance_approved_at'    => now(),
                                'disbursement_status'    => 'Sudah Dicairkan',
                            ]);
                            Notification::make()
                                ->title('Pengembalian dikonfirmasi!')
                                ->success()
                                ->send();
                        }
                    }),
            ]);
    }

    /**
     * Helper: Hitung sisa dana BBM bulan ini
     */
    private static function getSisaForEmployee(Employee $employee): float
    {
        $budget = $employee->bbm_budget;
        $used   = $employee->getUsedBbmForPeriod(now()->month, now()->year);
        $sisa   = $budget - $used;
        return max(0, $sisa); // Sisa hanya bisa positif (0 jika over budget)
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBbmReturnSaldos::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransportExpenseReportResource\Pages;
use App\Models\Claim;
use App\Services\ClaimExcelExportService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransportExpenseReportResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'REKAPAN BIAYA';

    protected static ?string $modelLabel = 'Rekapan Biaya Transportasi';

    protected static ?string $pluralModelLabel = 'Rekapan Biaya Transportasi';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where(function ($q) {
                $q->where('claim_category', 'transport_entertain')
                  ->orWhere('claim_type', 'like', '%Transport%')
                  ->orWhere('claim_type', 'like', '%Tol%')
                  ->orWhere('items', 'like', '%Transport%')
                  ->orWhere('items', 'like', '%Tol%');
            })
            ->with(['employee.role', 'employee.positionModel', 'branch', 'claimPeriod.employee']);

        $user = auth()->user();
        if ($user && !$user->isSuperAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                $empId = $user->getEffectiveEmployeeId();
                if ($empId) {
                    $q->orWhere('employee_id', $empId);
                }
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('claim_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('_uid')
                    ->label('_UID')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Nama Pemohon')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->state(fn (Claim $record) => $record->effective_employee?->name ?? '-')
                    ->description(fn (Claim $record) => $record->effective_employee?->position_name ?? '-'),

                Tables\Columns\TextColumn::make('claim_type_string')
                    ->label('Jenis Transport')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('purpose_string')
                    ->label('Keperluan Perjalanan')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('note')
                    ->label('Keterangan / Lokasi')
                    ->limit(25)
                    ->searchable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Area / Kota')
                    ->default('-'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal Biaya')
                    ->money('IDR')
                    ->weight('bold')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total Transportasi')),

                Tables\Columns\TextColumn::make('service_motor_budget')
                    ->label('Batas Plafon')
                    ->state(fn (Claim $record) => (float)($record->effective_employee?->service_motor_budget ?? 0))
                    ->money('IDR')
                    ->weight('medium')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('transport_budget_status')
                    ->label('Peringatan / Status Plafon')
                    ->state(fn (Claim $record) => $record->transport_budget_status['label'])
                    ->badge()
                    ->color(fn (Claim $record) => $record->transport_budget_status['color']),

                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ACC_PAK_JEJEN', 'DISETUJUI', 'ACC_RGM' => 'success',
                        'DITOLAK' => 'danger',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('disbursement_status')
                    ->label('Pencairan')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sudah Dicairkan' ? 'success' : 'danger'),
            ])
            ->defaultSort('claim_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Pemohon (Per Orang / Keseluruhan)')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Semua Karyawan (Keseluruhan)'),

                Tables\Filters\SelectFilter::make('budget_warning')
                    ->label('Peringatan Batas Plafon')
                    ->options([
                        'over' => '⚠️ Melebihi Batas (Over Budget)',
                        'safe' => '✅ Sesuai Plafon (Aman)',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (($data['value'] ?? null) === 'over') {
                            $overEmpIds = \App\Models\Employee::all()->filter(fn ($e) => $e->service_motor_budget > 0 && $e->getUsedTransportForPeriod() > $e->service_motor_budget)->pluck('id');
                            return $query->whereIn('employee_id', $overEmpIds);
                        }
                        if (($data['value'] ?? null) === 'safe') {
                            $safeEmpIds = \App\Models\Employee::all()->filter(fn ($e) => $e->service_motor_budget <= 0 || $e->getUsedTransportForPeriod() <= $e->service_motor_budget)->pluck('id');
                            return $query->whereIn('employee_id', $safeEmpIds);
                        }
                        return $query;
                    }),

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

                Tables\Filters\SelectFilter::make('has_uid')
                    ->label('Status _UID')
                    ->options([
                        'yes' => 'Sudah Ada _UID',
                        'no' => 'Belum Ada _UID',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (($data['value'] ?? null) === 'yes') return $query->whereNotNull('_uid');
                        if (($data['value'] ?? null) === 'no') return $query->whereNull('_uid');
                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('disbursement_status')
                    ->label('Pencairan')
                    ->options(['Belum Dicairkan' => 'Belum Dicairkan', 'Sudah Dicairkan' => 'Sudah Dicairkan']),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_transport_excel')
                    ->label('Download Rekapan Transportasi (Excel)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(function (\Filament\Tables\Contracts\HasTable $livewire) {
                        $claims = $livewire->getFilteredTableQuery()
                            ->with(['employee.role', 'branch'])
                            ->orderBy('claim_date', 'asc')
                            ->get();

                        return app(ClaimExcelExportService::class)->exportTransportRecapExcel($claims);
                    }),
            ])
            ->actions([
                \App\Filament\Actions\ViewTransferProofAction::make(),

                Tables\Actions\Action::make('download_single_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportSingleClaim($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportExpenseReports::route('/'),
        ];
    }
}

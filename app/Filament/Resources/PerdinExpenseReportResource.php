<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerdinExpenseReportResource\Pages;
use App\Models\Claim;
use App\Services\ClaimExcelExportService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PerdinExpenseReportResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'REKAPAN BIAYA';

    protected static ?string $modelLabel = 'Rekapan Perjalanan Dinas';

    protected static ?string $pluralModelLabel = 'Rekapan Biaya Perjalanan Dinas';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Rekapan Perdin dinonaktifkan karena telah terintegrasi di menu Perjalanan Dinas
     */
    public static function canViewAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where(function ($q) {
                $q->where('is_perdin', true)
                  ->orWhere('claim_category', 'perdin');
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
                    ->label('Tgl Berangkat')
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

                Tables\Columns\TextColumn::make('destination_city')
                    ->label('Rute Perjalanan')
                    ->state(fn (Claim $record) => ($record->homebase ?: 'Purwokerto') . ' ➔ ' . ($record->destination_city ?: 'Tujuan'))
                    ->description(fn (Claim $record) => "{$record->distance_km} KM ({$record->days_count} Hari / {$record->nights_count} Malam)"),

                Tables\Columns\TextColumn::make('meal_allowance')
                    ->label('Uang Makan')
                    ->money('IDR')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total Makan')),

                Tables\Columns\TextColumn::make('lodging_allowance')
                    ->label('Penginapan')
                    ->money('IDR')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total Inap')),

                Tables\Columns\TextColumn::make('toll_cost')
                    ->label('Tol')
                    ->money('IDR')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total Tol')),

                Tables\Columns\TextColumn::make('fuel_cost')
                    ->label('BBM Perdin')
                    ->money('IDR')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Total BBM')),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Total Klaim')
                    ->money('IDR')
                    ->weight('bold')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')->label('Grand Total Perdin')),

                Tables\Columns\TextColumn::make('perdin_budget')
                    ->label('Batas Plafon')
                    ->state(fn (Claim $record) => (float)($record->effective_employee?->perdin_budget ?? 0))
                    ->money('IDR')
                    ->weight('medium')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('perdin_budget_status')
                    ->label('Peringatan / Status Plafon')
                    ->state(fn (Claim $record) => $record->perdin_budget_status['label'])
                    ->badge()
                    ->color(fn (Claim $record) => $record->perdin_budget_status['color']),

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
                            $overEmpIds = \App\Models\Employee::all()->filter(fn ($e) => $e->perdin_budget > 0 && $e->used_perdin > $e->perdin_budget)->pluck('id');
                            return $query->whereIn('employee_id', $overEmpIds);
                        }
                        if (($data['value'] ?? null) === 'safe') {
                            $safeEmpIds = \App\Models\Employee::all()->filter(fn ($e) => $e->perdin_budget <= 0 || $e->used_perdin <= $e->perdin_budget)->pluck('id');
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
                Tables\Actions\Action::make('export_perdin_excel')
                    ->label('Download Rekapan Perdin (Excel)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(function (\Filament\Tables\Contracts\HasTable $livewire) {
                        $claims = $livewire->getFilteredTableQuery()
                            ->with(['employee.role', 'branch'])
                            ->orderBy('claim_date', 'asc')
                            ->get();

                        return app(ClaimExcelExportService::class)->exportPerdinRecapExcel($claims);
                    }),
            ])
            ->actions([
                \App\Filament\Actions\ViewTransferProofAction::make(),

                Tables\Actions\Action::make('download_form_perdin')
                    ->label('Form Perdin')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(fn (Claim $record) => app(ClaimExcelExportService::class)->exportPerdinForm($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerdinExpenseReports::route('/'),
        ];
    }
}

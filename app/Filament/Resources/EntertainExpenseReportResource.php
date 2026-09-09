<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EntertainExpenseReportResource\Pages;
use App\Models\Employee;
use App\Services\ClaimExcelExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EntertainExpenseReportResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'REKAPAN BIAYA';

    protected static ?string $modelLabel = 'Rekapan Biaya Entertain & Transport';

    protected static ?string $pluralModelLabel = 'Rekapan Biaya Entertain & Transport';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Admin dan SuperAdmin dapat mengakses Rekapan Biaya Entertain & Transport
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && ($user->isAdmin() || $user->isSuperAdmin());
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['positionModel', 'role', 'claims'])
            ->where('status', 'Aktif');

        $user = auth()->user();
        if ($user && !$user->isSuperAdmin()) {
            $regions = $user->getRegionList();
            if (!empty($regions)) {
                $query->whereIn('region', $regions);
            } else {
                $empId = $user->getEffectiveEmployeeId();
                if ($empId) {
                    $query->where('id', $empId);
                }
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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Karyawan')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Employee $record) => ($record->position_name ?: '-') . ($record->region ? " • {$record->region}" : ($record->homebase ? " • {$record->homebase}" : ''))),

                Tables\Columns\TextColumn::make('position_name')
                    ->label('Jabatan / Role')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('region')
                    ->label('Area / Region')
                    ->state(fn (Employee $record) => $record->region ?: ($record->homebase ?: '-'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('entertain_budget')
                    ->label('Batas Plafon Entertain')
                    ->money('IDR')
                    ->weight('semibold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('used_entertain')
                    ->label('Realisasi Terpakai')
                    ->money('IDR')
                    ->weight('bold')
                    ->state(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        return (float)$record->getUsedEntertainForPeriod($month ? (int)$month : null, $year ? (int)$year : null, true);
                    }),

                Tables\Columns\TextColumn::make('sisa_saldo')
                    ->label('Sisa Saldo')
                    ->state(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->entertain_budget;
                        $used = (float)$record->getUsedEntertainForPeriod($month ? (int)$month : null, $year ? (int)$year : null, true);
                        if ($budget <= 0) return '-';
                        $diff = $budget - $used;
                        return ($diff < 0 ? '⚠️ -Rp ' : 'Rp ') . number_format(abs($diff), 0, ',', '.');
                    })
                    ->weight('bold')
                    ->color(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->entertain_budget;
                        $used = (float)$record->getUsedEntertainForPeriod($month ? (int)$month : null, $year ? (int)$year : null, true);
                        if ($budget <= 0) return 'gray';
                        return ($budget - $used < 0) ? 'danger' : 'success';
                    }),

                Tables\Columns\TextColumn::make('transactions_count')
                    ->label('Jml Klaim')
                    ->badge()
                    ->color('gray')
                    ->state(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        return $record->getEntertainClaimsCountForPeriod($month ? (int)$month : null, $year ? (int)$year : null) . ' Transaksi';
                    }),

                Tables\Columns\TextColumn::make('budget_status')
                    ->label('Status Plafon')
                    ->state(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->entertain_budget;
                        $used = (float)$record->getUsedEntertainForPeriod($month ? (int)$month : null, $year ? (int)$year : null, true);
                        if ($budget <= 0) return 'Tanpa Batas Plafon';
                        return ($budget - $used < 0) ? '⚠️ Melebihi Plafon' : '✅ Sesuai Plafon';
                    })
                    ->badge()
                    ->color(function (string $state) {
                        if (str_contains($state, 'Melebihi')) return 'danger';
                        if (str_contains($state, 'Sesuai')) return 'success';
                        return 'gray';
                    }),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('month')
                    ->label('Bulan')
                    ->options([
                        '1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April',
                        '5' => 'Mei', '6' => 'Juni', '7' => 'Juli', '8' => 'Agustus',
                        '9' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                    ])
                    ->placeholder('Semua Bulan (All-Time)'),

                Tables\Filters\SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(['2024' => '2024', '2025' => '2025', '2026' => '2026', '2027' => '2027']),

                Tables\Filters\SelectFilter::make('budget_warning')
                    ->label('Peringatan Batas Plafon')
                    ->options([
                        'over' => '⚠️ Melebihi Batas (Over Budget)',
                        'safe' => '✅ Sesuai Plafon (Aman)',
                    ])
                    ->query(function (Builder $query, array $data, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $m = $month ? (int)$month : null;
                        $y = $year ? (int)$year : null;

                        if (($data['value'] ?? null) === 'over') {
                            $overEmpIds = Employee::all()->filter(fn ($e) => $e->entertain_budget > 0 && $e->getUsedEntertainForPeriod($m, $y, true) > $e->entertain_budget)->pluck('id');
                            return $query->whereIn('id', $overEmpIds);
                        }
                        if (($data['value'] ?? null) === 'safe') {
                            $safeEmpIds = Employee::all()->filter(fn ($e) => $e->entertain_budget <= 0 || $e->getUsedEntertainForPeriod($m, $y, true) <= $e->entertain_budget)->pluck('id');
                            return $query->whereIn('id', $safeEmpIds);
                        }
                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Role / Jabatan')
                    ->relationship('role', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_entertain_users_recap_excel')
                    ->label('Download Rekapan Keseluruhan (Excel)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(function (Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $employees = $livewire->getFilteredTableQuery()->get();

                        return app(ClaimExcelExportService::class)->exportEntertainUsersSummaryRecap(
                            $employees,
                            $month ? (int)$month : null,
                            $year ? (int)$year : null
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download_user_entertain_excel')
                    ->label('Download Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->form([
                        Forms\Components\Radio::make('period_choice')
                            ->label('Pilihan Periode Transaksi')
                            ->options([
                                'table_filter' => 'Sesuai Filter Tabel Saat Ini',
                                'all_time' => 'Semua Transaksi (All-Time)',
                                'custom' => 'Pilih Periode Tertentu',
                            ])
                            ->default('table_filter')
                            ->reactive(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('custom_month')
                                    ->label('Bulan')
                                    ->options([
                                        '1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April',
                                        '5' => 'Mei', '6' => 'Juni', '7' => 'Juli', '8' => 'Agustus',
                                        '9' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                                    ])
                                    ->default(date('n')),

                                Forms\Components\Select::make('custom_year')
                                    ->label('Tahun')
                                    ->options(['2024' => '2024', '2025' => '2025', '2026' => '2026', '2027' => '2027'])
                                    ->default(date('Y')),
                            ])
                            ->visible(fn (callable $get) => $get('period_choice') === 'custom'),
                    ])
                    ->action(function (Employee $record, array $data, Tables\Contracts\HasTable $livewire) {
                        $choice = $data['period_choice'] ?? 'table_filter';
                        $month = null;
                        $year = null;

                        if ($choice === 'table_filter') {
                            $m = $livewire->tableFilters['month']['value'] ?? null;
                            $y = $livewire->tableFilters['year']['value'] ?? null;
                            $month = $m ? (int)$m : null;
                            $year = $y ? (int)$y : null;
                        } elseif ($choice === 'custom') {
                            $month = !empty($data['custom_month']) ? (int)$data['custom_month'] : null;
                            $year = !empty($data['custom_year']) ? (int)$data['custom_year'] : null;
                        }

                        return app(ClaimExcelExportService::class)->exportUserEntertainTransactions($record, $month, $year);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntertainExpenseReports::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserExpenseReportResource\Pages;
use App\Models\Claim;
use App\Models\Employee;
use App\Services\ClaimExcelExportService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserExpenseReportResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'REKAPAN BIAYA';

    protected static ?string $modelLabel = 'Rekapan Sisa Saldo & Plafon';

    protected static ?string $pluralModelLabel = 'Rekapan Sisa Saldo & Plafon';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withSum('claims', 'amount')
            ->with(['positionModel', 'role', 'claims']);

        $user = auth()->user();
        if ($user && !$user->isSuperAdmin()) {
            $empId = $user->getEffectiveEmployeeId();
            if ($empId) {
                $query->where('id', $empId);
            } else {
                $query->whereHas('claims', fn ($q) => $q->where('user_id', $user->id));
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
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Employee $record) => ($record->position_name) . ($record->homebase ? " • {$record->homebase}" : '')),

                // 1. BBM Column
                Tables\Columns\TextColumn::make('sisa_bbm')
                    ->label('BBM (Plafon / Sisa)')
                    ->state(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->bbm_budget;
                        $used = (float)$record->getUsedBbmForPeriod($month ? (int)$month : null, $year ? (int)$year : null);

                        if ($budget <= 0 && $used <= 0) return '-';
                        if ($budget <= 0) return 'Pakai: Rp ' . number_format($used, 0, ',', '.');
                        $diff = $budget - $used;
                        return ($diff < 0 ? '⚠️ -Rp ' : 'Rp ') . number_format(abs($diff), 0, ',', '.');
                    })
                    ->description(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->bbm_budget;
                        $used = (float)$record->getUsedBbmForPeriod($month ? (int)$month : null, $year ? (int)$year : null);
                        if ($budget <= 0 && $used <= 0) return null;
                        return "Plafon: " . number_format($budget / 1000, 0) . "k | Pakai: " . number_format($used / 1000, 0) . "k";
                    })
                    ->weight('semibold')
                    ->color(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->bbm_budget;
                        $used = (float)$record->getUsedBbmForPeriod($month ? (int)$month : null, $year ? (int)$year : null);
                        if ($budget <= 0) return 'gray';
                        return ($budget - $used < 0) ? 'danger' : 'success';
                    }),

                // 2. Entertain Column
                Tables\Columns\TextColumn::make('sisa_entertain')
                    ->label('Entertain (Plafon / Sisa)')
                    ->state(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->entertain_budget;
                        $used = (float)$record->getUsedEntertainForPeriod($month ? (int)$month : null, $year ? (int)$year : null, true);

                        if ($budget <= 0 && $used <= 0) return '-';
                        if ($budget <= 0) return 'Pakai: Rp ' . number_format($used, 0, ',', '.');
                        $diff = $budget - $used;
                        return ($diff < 0 ? '⚠️ -Rp ' : 'Rp ') . number_format(abs($diff), 0, ',', '.');
                    })
                    ->description(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->entertain_budget;
                        $used = (float)$record->getUsedEntertainForPeriod($month ? (int)$month : null, $year ? (int)$year : null, true);
                        if ($budget <= 0 && $used <= 0) return null;
                        return "Plafon: " . number_format($budget / 1000, 0) . "k | Pakai: " . number_format($used / 1000, 0) . "k";
                    })
                    ->weight('semibold')
                    ->color(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->entertain_budget;
                        $used = (float)$record->getUsedEntertainForPeriod($month ? (int)$month : null, $year ? (int)$year : null, true);
                        if ($budget <= 0) return 'gray';
                        return ($budget - $used < 0) ? 'danger' : 'success';
                    }),

                // 3. Perdin Column
                Tables\Columns\TextColumn::make('sisa_perdin')
                    ->label('Perdin (Plafon / Sisa)')
                    ->state(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->perdin_budget;
                        $used = (float)$record->getUsedPerdinForPeriod($month ? (int)$month : null, $year ? (int)$year : null);

                        if ($budget <= 0 && $used <= 0) return '-';
                        if ($budget <= 0) return 'Pakai: Rp ' . number_format($used, 0, ',', '.');
                        $diff = $budget - $used;
                        return ($diff < 0 ? '⚠️ -Rp ' : 'Rp ') . number_format(abs($diff), 0, ',', '.');
                    })
                    ->description(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->perdin_budget;
                        $used = (float)$record->getUsedPerdinForPeriod($month ? (int)$month : null, $year ? (int)$year : null);
                        if ($budget <= 0 && $used <= 0) return null;
                        return "Plafon: " . number_format($budget / 1000, 0) . "k | Pakai: " . number_format($used / 1000, 0) . "k";
                    })
                    ->weight('semibold')
                    ->color(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->perdin_budget;
                        $used = (float)$record->getUsedPerdinForPeriod($month ? (int)$month : null, $year ? (int)$year : null);
                        if ($budget <= 0) return 'gray';
                        return ($budget - $used < 0) ? 'danger' : 'success';
                    }),

                // 4. Service / Transport Column
                Tables\Columns\TextColumn::make('sisa_service')
                    ->label('Service / Transport')
                    ->state(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->service_motor_budget;
                        $used = (float)$record->getUsedTransportForPeriod($month ? (int)$month : null, $year ? (int)$year : null);

                        if ($budget <= 0 && $used <= 0) return '-';
                        if ($budget <= 0) return 'Pakai: Rp ' . number_format($used, 0, ',', '.');
                        $diff = $budget - $used;
                        return ($diff < 0 ? '⚠️ -Rp ' : 'Rp ') . number_format(abs($diff), 0, ',', '.');
                    })
                    ->description(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->service_motor_budget;
                        $used = (float)$record->getUsedTransportForPeriod($month ? (int)$month : null, $year ? (int)$year : null);
                        if ($budget <= 0 && $used <= 0) return null;
                        return "Plafon: " . number_format($budget / 1000, 0) . "k | Pakai: " . number_format($used / 1000, 0) . "k";
                    })
                    ->weight('semibold')
                    ->color(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->service_motor_budget;
                        $used = (float)$record->getUsedTransportForPeriod($month ? (int)$month : null, $year ? (int)$year : null);
                        if ($budget <= 0) return 'gray';
                        return ($budget - $used < 0) ? 'danger' : 'success';
                    }),

                // 5. Total Sisa Saldo Column
                Tables\Columns\TextColumn::make('total_remaining')
                    ->label('Total Sisa Saldo')
                    ->state(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->total_budget;
                        $used = (float)$record->getTotalExpenseUsedForPeriod($month ? (int)$month : null, $year ? (int)$year : null);

                        if ($budget <= 0) return 'Pakai: Rp ' . number_format($used, 0, ',', '.');
                        $diff = $budget - $used;
                        return ($diff < 0 ? '-Rp ' : 'Rp ') . number_format(abs($diff), 0, ',', '.');
                    })
                    ->description(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->total_budget;
                        $used = (float)$record->getTotalExpenseUsedForPeriod($month ? (int)$month : null, $year ? (int)$year : null);
                        if ($budget <= 0) return 'Tanpa Batas Plafon Total';
                        return "Total Plafon: Rp " . number_format($budget, 0, ',', '.') . " | Terpakai: Rp " . number_format($used, 0, ',', '.');
                    })
                    ->weight('bold')
                    ->color(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $budget = (float)$record->total_budget;
                        $used = (float)$record->getTotalExpenseUsedForPeriod($month ? (int)$month : null, $year ? (int)$year : null);
                        if ($budget <= 0) return 'gray';
                        return ($budget - $used < 0) ? 'danger' : 'success';
                    }),

                // 6. Status & Peringatan
                Tables\Columns\TextColumn::make('budget_status')
                    ->label('Status Peringatan Saldo')
                    ->state(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $m = $month ? (int)$month : null;
                        $y = $year ? (int)$year : null;

                        $bbmBudget = (float)$record->bbm_budget;
                        $bbmUsed = (float)$record->getUsedBbmForPeriod($m, $y);

                        $entBudget = (float)$record->entertain_budget;
                        $entUsed = (float)$record->getUsedEntertainForPeriod($m, $y, true);

                        $perdinBudget = (float)$record->perdin_budget;
                        $perdinUsed = (float)$record->getUsedPerdinForPeriod($m, $y);

                        $srvBudget = (float)$record->service_motor_budget;
                        $srvUsed = (float)$record->getUsedTransportForPeriod($m, $y);

                        $alerts = [];
                        if ($bbmBudget > 0 && $bbmUsed > $bbmBudget) {
                            $alerts[] = 'BBM (+Rp ' . number_format($bbmUsed - $bbmBudget, 0, ',', '.') . ')';
                        }
                        if ($entBudget > 0 && $entUsed > $entBudget) {
                            $alerts[] = 'Entertain (+Rp ' . number_format($entUsed - $entBudget, 0, ',', '.') . ')';
                        }
                        if ($perdinBudget > 0 && $perdinUsed > $perdinBudget) {
                            $alerts[] = 'Perdin (+Rp ' . number_format($perdinUsed - $perdinBudget, 0, ',', '.') . ')';
                        }
                        if ($srvBudget > 0 && $srvUsed > $srvBudget) {
                            $alerts[] = 'Service (+Rp ' . number_format($srvUsed - $srvBudget, 0, ',', '.') . ')';
                        }

                        if (!empty($alerts)) {
                            return '⚠️ LEWAT BATAS: ' . implode(', ', $alerts);
                        }

                        $totalBudget = (float)$record->total_budget;
                        $totalUsed = (float)$record->getTotalExpenseUsedForPeriod($m, $y);
                        if ($totalBudget <= 0) {
                            return 'Tanpa Batas Plafon';
                        }
                        if ($totalBudget - $totalUsed < 0) {
                            return '⚠️ OVER TOTAL (+Rp ' . number_format($totalUsed - $totalBudget, 0, ',', '.') . ')';
                        }
                        return '✅ Sisa Saldo Aman';
                    })
                    ->badge()
                    ->color(function (string $state) {
                        if (str_contains($state, 'LEWAT BATAS') || str_contains($state, 'OVER')) return 'danger';
                        if (str_contains($state, 'Aman')) return 'success';
                        return 'gray';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('month')
                    ->label('Bulan')
                    ->options([
                        '1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April',
                        '5' => 'Mei', '6' => 'Juni', '7' => 'Juli', '8' => 'Agustus',
                        '9' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                    ]),

                Tables\Filters\SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(['2024' => '2024', '2025' => '2025', '2026' => '2026', '2027' => '2027']),

                Tables\Filters\SelectFilter::make('position_id')
                    ->label('Jabatan / Role')
                    ->relationship('positionModel', 'name')
                    ->preload(),

                Tables\Filters\SelectFilter::make('budget_status')
                    ->label('Peringatan Status Saldo')
                    ->options([
                        'over_any' => '⚠️ Ada Kategori Over Budget',
                        'over_bbm' => '⚠️ Over Budget BBM',
                        'over_entertain' => '⚠️ Over Budget Entertain',
                        'safe_all' => '✅ Semua Saldo Aman',
                    ])
                    ->query(function (Builder $query, array $data, Tables\Contracts\HasTable $livewire) {
                        $val = $data['value'] ?? null;
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;
                        $m = $month ? (int)$month : null;
                        $y = $year ? (int)$year : null;

                        if ($val === 'over_any') {
                            $ids = Employee::all()->filter(function ($e) use ($m, $y) {
                                return ($e->bbm_budget > 0 && $e->getUsedBbmForPeriod($m, $y) > $e->bbm_budget) ||
                                       ($e->entertain_budget > 0 && $e->getUsedEntertainForPeriod($m, $y, true) > $e->entertain_budget) ||
                                       ($e->perdin_budget > 0 && $e->getUsedPerdinForPeriod($m, $y) > $e->perdin_budget) ||
                                       ($e->service_motor_budget > 0 && $e->getUsedTransportForPeriod($m, $y) > $e->service_motor_budget);
                            })->pluck('id');
                            return $query->whereIn('id', $ids);
                        }
                        if ($val === 'over_bbm') {
                            $ids = Employee::all()->filter(fn ($e) => $e->bbm_budget > 0 && $e->getUsedBbmForPeriod($m, $y) > $e->bbm_budget)->pluck('id');
                            return $query->whereIn('id', $ids);
                        }
                        if ($val === 'over_entertain') {
                            $ids = Employee::all()->filter(fn ($e) => $e->entertain_budget > 0 && $e->getUsedEntertainForPeriod($m, $y, true) > $e->entertain_budget)->pluck('id');
                            return $query->whereIn('id', $ids);
                        }
                        if ($val === 'safe_all') {
                            $ids = Employee::all()->filter(function ($e) use ($m, $y) {
                                return ($e->bbm_budget <= 0 || $e->getUsedBbmForPeriod($m, $y) <= $e->bbm_budget) &&
                                       ($e->entertain_budget <= 0 || $e->getUsedEntertainForPeriod($m, $y, true) <= $e->entertain_budget) &&
                                       ($e->perdin_budget <= 0 || $e->getUsedPerdinForPeriod($m, $y) <= $e->perdin_budget);
                            })->pluck('id');
                            return $query->whereIn('id', $ids);
                        }
                        return $query;
                    }),

                // Filter by Nama Karyawan
                Tables\Filters\SelectFilter::make('employee_name')
                    ->label('Nama Karyawan')
                    ->options(fn () => \App\Models\Employee::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => !empty($data['value']) ? $query->where('id', $data['value']) : $query),

                // Filter by Role/Jabatan
                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Role / Jabatan')
                    ->relationship('role', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                // 1. Export Rekapan Budget Semua Karyawan (Excel)
                Tables\Actions\Action::make('export_budget_recap_excel')
                    ->label('Export Rekap Budget (Excel)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;

                        $employees = $livewire->getFilteredTableQuery()->with(['role', 'positionModel', 'claims'])->get();
                        return app(ClaimExcelExportService::class)->exportUserBudgetRecapExcel($employees, $month ? (int)$month : null, $year ? (int)$year : null);
                    }),

                // 2. Export Rekapan Budget Semua Karyawan (PDF)
                Tables\Actions\Action::make('export_budget_recap_pdf')
                    ->label('Export Rekap Budget (PDF)')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->action(function (Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;

                        $employees = $livewire->getFilteredTableQuery()->with(['role', 'positionModel', 'claims'])->get();
                        return app(\App\Services\UserExpenseRecapPdfExportService::class)->exportAllUsersRecapPdf($employees, $month ? (int)$month : null, $year ? (int)$year : null);
                    }),

                // 3. Template Rekapan Sisa Klaim Transaksi
                Tables\Actions\Action::make('export_sisa_klaim_template')
                    ->label('Template Rekapan Sisa Klaim')
                    ->icon('heroicon-o-table-cells')
                    ->color('primary')
                    ->action(function (Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;

                        $claimsQuery = Claim::with(['employee.role', 'employee.positionModel', 'branch'])->orderBy('claim_date', 'asc');
                        if ($month) $claimsQuery->whereMonth('claim_date', $month);
                        if ($year) $claimsQuery->whereYear('claim_date', $year);
                        $claims = $claimsQuery->get();

                        $employees = $livewire->getFilteredTableQuery()->with(['role', 'positionModel', 'claims'])->get();
                        return app(ClaimExcelExportService::class)->exportSisaKlaimRecapTemplate($claims, $employees, $month, $year);
                    }),

                // 4. Rekap Budget Entertain
                Tables\Actions\Action::make('export_entertain_recap')
                    ->label('Rekap Budget Entertain')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('gray')
                    ->action(function (Tables\Contracts\HasTable $livewire) {
                        $employees = $livewire->getFilteredTableQuery()->with(['positionModel', 'role', 'claims'])->get();
                        return app(ClaimExcelExportService::class)->exportEntertainBudgetRecap($employees);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_balance_details')
                    ->label('Detail Saldo')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (Employee $record) => "Rincian Sisa Saldo & Plafon: {$record->name}")
                    ->modalDescription(fn (Employee $record) => "Jabatan: " . ($record->position_name) . " | Homebase: " . ($record->homebase ?: '-'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (Employee $record, Tables\Contracts\HasTable $livewire) => view('filament.modals.employee-balance-detail', [
                        'employee' => $record,
                        'month' => $livewire->tableFilters['month']['value'] ?? null,
                        'year' => $livewire->tableFilters['year']['value'] ?? null,
                    ])),

                Tables\Actions\Action::make('export_user_claims')
                    ->label('Excel User')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;

                        $query = $record->claims()->orderBy('claim_date', 'asc');
                        if ($month) $query->whereMonth('claim_date', $month);
                        if ($year) $query->whereYear('claim_date', $year);
                        $claims = $query->get();

                        if ($claims->isEmpty()) {
                            return null;
                        }
                        return app(ClaimExcelExportService::class)->exportClaims($claims, "REALISASI KLAIM — {$record->name}");
                    }),

                Tables\Actions\Action::make('export_user_pdf')
                    ->label('PDF User')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->action(function (Employee $record, Tables\Contracts\HasTable $livewire) {
                        $month = $livewire->tableFilters['month']['value'] ?? null;
                        $year = $livewire->tableFilters['year']['value'] ?? null;

                        return app(\App\Services\UserExpenseRecapPdfExportService::class)->exportSingleUserRecapPdf($record, $month ? (int)$month : null, $year ? (int)$year : null);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserExpenseReports::route('/'),
        ];
    }
}

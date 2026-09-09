<?php

namespace App\Filament\Resources\BbmExpenseReportResource\Pages;

use App\Filament\Resources\BbmExpenseReportResource;
use App\Models\Employee;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBbmExpenseReports extends ListRecords
{
    protected static string $resource = BbmExpenseReportResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Karyawan')
                ->icon('heroicon-o-users'),

            'over_budget' => Tab::make('⚠️ Melebihi Plafon')
                ->icon('heroicon-o-exclamation-triangle')
                ->badge(function () {
                    $count = Employee::where('status', 'Aktif')->get()->filter(fn ($e) => $e->bbm_budget > 0 && $e->getUsedBbmForPeriod() > $e->bbm_budget)->count();
                    return $count > 0 ? $count : null;
                })
                ->badgeColor('danger')
                ->modifyQueryUsing(function (Builder $query) {
                    $overEmpIds = Employee::all()->filter(fn ($e) => $e->bbm_budget > 0 && $e->getUsedBbmForPeriod() > $e->bbm_budget)->pluck('id');
                    return $query->whereIn('id', $overEmpIds);
                }),

            'safe' => Tab::make('✅ Sesuai Plafon (Aman)')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(function (Builder $query) {
                    $safeEmpIds = Employee::all()->filter(fn ($e) => $e->bbm_budget <= 0 || $e->getUsedBbmForPeriod() <= $e->bbm_budget)->pluck('id');
                    return $query->whereIn('id', $safeEmpIds);
                }),
        ];
    }
}

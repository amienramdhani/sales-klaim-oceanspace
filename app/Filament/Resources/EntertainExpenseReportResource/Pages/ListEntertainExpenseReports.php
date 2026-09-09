<?php

namespace App\Filament\Resources\EntertainExpenseReportResource\Pages;

use App\Filament\Resources\EntertainExpenseReportResource;
use App\Models\Employee;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListEntertainExpenseReports extends ListRecords
{
    protected static string $resource = EntertainExpenseReportResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Karyawan')
                ->icon('heroicon-o-users'),

            'over_budget' => Tab::make('⚠️ Melebihi Plafon')
                ->icon('heroicon-o-exclamation-triangle')
                ->badge(function () {
                    $count = Employee::where('status', 'Aktif')->get()->filter(fn ($e) => $e->entertain_budget > 0 && $e->getUsedEntertainForPeriod(null, null, true) > $e->entertain_budget)->count();
                    return $count > 0 ? $count : null;
                })
                ->badgeColor('danger')
                ->modifyQueryUsing(function (Builder $query) {
                    $overEmpIds = Employee::all()->filter(fn ($e) => $e->entertain_budget > 0 && $e->getUsedEntertainForPeriod(null, null, true) > $e->entertain_budget)->pluck('id');
                    return $query->whereIn('id', $overEmpIds);
                }),

            'safe' => Tab::make('✅ Sesuai Plafon (Aman)')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(function (Builder $query) {
                    $safeEmpIds = Employee::all()->filter(fn ($e) => $e->entertain_budget <= 0 || $e->getUsedEntertainForPeriod(null, null, true) <= $e->entertain_budget)->pluck('id');
                    return $query->whereIn('id', $safeEmpIds);
                }),
        ];
    }
}

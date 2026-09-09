<?php

namespace App\Filament\Resources\UserExpenseReportResource\Pages;

use App\Filament\Resources\UserExpenseReportResource;
use App\Models\Employee;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUserExpenseReports extends ListRecords
{
    protected static string $resource = UserExpenseReportResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Karyawan')
                ->icon('heroicon-o-users'),

            'over_budget' => Tab::make('⚠️ Ada Over Budget')
                ->icon('heroicon-o-exclamation-triangle')
                ->badge(function () {
                    $ids = Employee::all()->filter(function ($e) {
                        return ($e->bbm_budget > 0 && $e->used_bbm > $e->bbm_budget) ||
                               ($e->entertain_budget > 0 && $e->used_entertain_makan > $e->entertain_budget) ||
                               ($e->perdin_budget > 0 && $e->used_perdin > $e->perdin_budget) ||
                               ($e->service_motor_budget > 0 && $e->getUsedTransportForPeriod() > $e->service_motor_budget);
                    })->pluck('id');
                    return $ids->count() > 0 ? $ids->count() : null;
                })
                ->badgeColor('danger')
                ->modifyQueryUsing(function (Builder $query) {
                    $ids = Employee::all()->filter(function ($e) {
                        return ($e->bbm_budget > 0 && $e->used_bbm > $e->bbm_budget) ||
                               ($e->entertain_budget > 0 && $e->used_entertain_makan > $e->entertain_budget) ||
                               ($e->perdin_budget > 0 && $e->used_perdin > $e->perdin_budget) ||
                               ($e->service_motor_budget > 0 && $e->getUsedTransportForPeriod() > $e->service_motor_budget);
                    })->pluck('id');
                    return $query->whereIn('id', $ids);
                }),

            'safe' => Tab::make('✅ Sisa Saldo Aman')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(function (Builder $query) {
                    $ids = Employee::all()->filter(function ($e) {
                        return ($e->bbm_budget <= 0 || $e->used_bbm <= $e->bbm_budget) &&
                               ($e->entertain_budget <= 0 || $e->used_entertain_makan <= $e->entertain_budget) &&
                               ($e->perdin_budget <= 0 || $e->used_perdin <= $e->perdin_budget);
                    })->pluck('id');
                    return $query->whereIn('id', $ids);
                }),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

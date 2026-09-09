<?php

namespace App\Filament\Resources\TransportExpenseReportResource\Pages;

use App\Filament\Resources\TransportExpenseReportResource;
use App\Models\Claim;
use App\Models\Employee;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTransportExpenseReports extends ListRecords
{
    protected static string $resource = TransportExpenseReportResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Keseluruhan Transaksi')
                ->icon('heroicon-o-queue-list'),

            'over_budget' => Tab::make('⚠️ Melebihi Batas (Over Budget)')
                ->icon('heroicon-o-exclamation-triangle')
                ->badge(function () {
                    $overEmpIds = Employee::all()->filter(fn ($e) => $e->service_motor_budget > 0 && $e->getUsedTransportForPeriod() > $e->service_motor_budget)->pluck('id');
                    $count = Claim::where(function ($q) {
                        $q->where('claim_category', 'transport_entertain')
                          ->orWhere('claim_type', 'like', '%Transport%')
                          ->orWhere('claim_type', 'like', '%Tol%')
                          ->orWhere('items', 'like', '%Transport%')
                          ->orWhere('items', 'like', '%Tol%');
                    })->whereIn('employee_id', $overEmpIds)->count();
                    return $count > 0 ? $count : null;
                })
                ->badgeColor('danger')
                ->modifyQueryUsing(function (Builder $query) {
                    $overEmpIds = Employee::all()->filter(fn ($e) => $e->service_motor_budget > 0 && $e->getUsedTransportForPeriod() > $e->service_motor_budget)->pluck('id');
                    return $query->whereIn('employee_id', $overEmpIds);
                }),

            'safe' => Tab::make('✅ Sesuai Plafon (Aman)')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(function (Builder $query) {
                    $safeEmpIds = Employee::all()->filter(fn ($e) => $e->service_motor_budget <= 0 || $e->getUsedTransportForPeriod() <= $e->service_motor_budget)->pluck('id');
                    return $query->whereIn('employee_id', $safeEmpIds);
                }),
        ];
    }
}

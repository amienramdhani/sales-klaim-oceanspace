<?php

namespace App\Filament\Widgets;

use App\Models\Claim;
use App\Models\Position;
use Filament\Widgets\Widget;

class PositionBudgetOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.position-budget-overview-widget';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public function getPositionsBudgetDataProperty()
    {
        $positions = Position::with('employees')->get();

        return $positions->map(function ($position) {
            $employeeIds = $position->employees->pluck('id')->toArray();

            // All claims for employees in this position
            $claims = Claim::where(function ($q) use ($employeeIds) {
                $q->whereIn('employee_id', $employeeIds)
                  ->orWhereHas('claimPeriod', function ($sub) use ($employeeIds) {
                      $sub->whereIn('employee_id', $employeeIds);
                  });
            })->get();

            $usedEntertain = 0;
            $usedOperational = 0;

            foreach ($claims as $c) {
                $typeStr = strtoupper($c->claim_type_string);
                if (str_contains($typeStr, 'ENTERTAIN')) {
                    $usedEntertain += (float)$c->amount;
                } else {
                    $usedOperational += (float)$c->amount;
                }
            }

            $usedTotal = $usedEntertain + $usedOperational;
            $entertainBudget = (float) $position->entertain_budget;
            $operationalBudget = (float) $position->operational_budget;
            $totalBudget = $entertainBudget + $operationalBudget;

            $remainingEntertain = $entertainBudget - $usedEntertain;
            $remainingOperational = $operationalBudget - $usedOperational;
            $remainingTotal = $totalBudget > 0 ? $totalBudget - $usedTotal : 0;

            $percentageUsed = $totalBudget > 0 ? min(100, round(($usedTotal / $totalBudget) * 100, 1)) : 0;

            return [
                'id' => $position->id,
                'name' => $position->name,
                'employees_count' => $position->employees->count(),
                'entertain_budget' => $entertainBudget,
                'used_entertain' => $usedEntertain,
                'remaining_entertain' => $remainingEntertain,
                'operational_budget' => $operationalBudget,
                'used_operational' => $usedOperational,
                'remaining_operational' => $remainingOperational,
                'total_budget' => $totalBudget,
                'used_total' => $usedTotal,
                'remaining_total' => $remainingTotal,
                'percentage_used' => $percentageUsed,
            ];
        });
    }
}

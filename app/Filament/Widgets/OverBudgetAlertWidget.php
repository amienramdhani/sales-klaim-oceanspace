<?php

namespace App\Filament\Widgets;

use App\Models\ClaimPeriod;
use App\Models\Employee;
use Filament\Widgets\Widget;

class OverBudgetAlertWidget extends Widget
{
    protected static string $view = 'filament.widgets.over-budget-alert-widget';

    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return false;
    }

    public function getOverBudgetEmployeesProperty()
    {
        return Employee::with(['role', 'positionModel', 'claims'])
            ->get()
            ->filter(function ($emp) {
                $budget = (float)$emp->entertain_budget > 0 ? (float)$emp->entertain_budget : (float)$emp->total_budget;
                $used = (float)$emp->total_expense_used;
                return $budget > 0 && $used > $budget;
            });
    }

    public function getOverBudgetPeriodsProperty()
    {
        return ClaimPeriod::where('over_budget', '>', 0)
            ->with('employee')
            ->latest()
            ->get();
    }
}

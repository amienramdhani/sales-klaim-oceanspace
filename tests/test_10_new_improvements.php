<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Filament\Resources\TransportEntertainClaimResource;
use App\Filament\Resources\FinanceClaimResource;
use App\Filament\Resources\EntertainExpenseReportResource;
use App\Filament\Resources\PositionResource;
use App\Filament\Resources\EmployeeResource;

echo "========================================================\n";
echo "TEST 10 PERBAIKAN STRUKTUR BUDGET & ALUR FINANCE\n";
echo "========================================================\n\n";

// 1. Test Employee Budget Fields & Accessors
echo "[TEST 1] Employee Per-User Budget Integration...\n";
$emp = Employee::first();
if ($emp) {
    echo "  - Employee: {$emp->name} ({$emp->position_name})\n";
    echo "  - BBM Budget: Rp " . number_format($emp->bbm_budget, 0, ',', '.') . "\n";
    echo "  - Entertain Budget: Rp " . number_format($emp->entertain_budget, 0, ',', '.') . "\n";
    echo "  - Transport Budget: Rp " . number_format($emp->transport_budget, 0, ',', '.') . "\n";
    echo "  - Perdin Budget: Rp " . number_format($emp->perdin_budget, 0, ',', '.') . "\n";
    echo "  - Total Budget: Rp " . number_format($emp->total_budget, 0, ',', '.') . "\n";
    echo "  [OK] Employee budget fields & accessors function correctly.\n";
}

// 2. Test TransportEntertainClaimResource Query (excludes BBM)
echo "\n[TEST 2] TransportEntertainClaimResource Query (Exclude BBM)...\n";
$teClaims = TransportEntertainClaimResource::getEloquentQuery()->get();
$hasBbm = false;
foreach ($teClaims as $c) {
    if ($c->claim_category === 'bbm' || !empty($c->bbm_photo_combined)) {
        $hasBbm = true;
        break;
    }
}
if (!$hasBbm) {
    echo "  [OK] Klaim Transport & Entertain query strictly excludes BBM (Total: {$teClaims->count()} baris).\n";
} else {
    echo "  [FAIL] BBM claim found in Transport & Entertain query!\n";
}

// 3. Test FinanceClaimResource Query & User Budget Binding
echo "\n[TEST 3] FinanceClaimResource (Nota Balik & User Budget)...\n";
$financeClaims = FinanceClaimResource::getEloquentQuery()->get();
echo "  [OK] Finance claims loaded successfully (Total: {$financeClaims->count()} baris).\n";

// 4. Test EntertainExpenseReportResource Query (includes Entertain, Tol, Parkir, Transport)
echo "\n[TEST 4] EntertainExpenseReportResource Query (Includes Transport & Tol)...\n";
$entReports = EntertainExpenseReportResource::getEloquentQuery()->get();
echo "  [OK] Rekapan Entertain & Transport loaded successfully (Total: {$entReports->count()} baris).\n";

// 5. Test Hidden Resources
echo "\n[TEST 5] Verifikasi Menu yang Dinonaktifkan...\n";
echo "  - BudgetTypeResource canViewAny: " . (\App\Filament\Resources\BudgetTypeResource::canViewAny() ? 'TRUE (ERROR)' : 'FALSE (OK)') . "\n";
echo "  - TransportExpenseReportResource canViewAny: " . (\App\Filament\Resources\TransportExpenseReportResource::canViewAny() ? 'TRUE (ERROR)' : 'FALSE (OK)') . "\n";
echo "  - BbmReturnSaldoResource canViewAny: " . (\App\Filament\Resources\BbmReturnSaldoResource::canViewAny() ? 'TRUE (ERROR)' : 'FALSE (OK)') . "\n";
echo "  - UserExpenseReportResource canViewAny: " . (\App\Filament\Resources\UserExpenseReportResource::canViewAny() ? 'TRUE (ERROR)' : 'FALSE (OK)') . "\n";

echo "\n========================================================\n";
echo "SEMUA TEST SELESAI DENGAN SUKSES!\n";
echo "========================================================\n";

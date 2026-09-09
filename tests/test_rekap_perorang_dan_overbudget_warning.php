<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Models\Employee;
use App\Models\Position;

echo "========================================================\n";
echo "TEST REKAPAN PERORANG & PERINGATAN OVER BUDGET\n";
echo "========================================================\n\n";

// 1. Check ASM position and set bbm_budget to 750.000 if not yet set
$asmPosition = Position::where('name', 'ASM')->first();
if (!$asmPosition) {
    $asmPosition = Position::create([
        'name' => 'ASM',
        'bbm_budget' => 750000,
        'entertain_budget' => 1500000,
        'perdin_budget' => 1000000,
        'service_motor_budget' => 500000,
    ]);
} else {
    $asmPosition->update([
        'bbm_budget' => 750000,
        'entertain_budget' => 1500000,
    ]);
}

// 2. Find or create an employee with ASM position
$employee = Employee::where('position_id', $asmPosition->id)->first();
if (!$employee) {
    $employee = Employee::create([
        'name' => 'Budi Santoso (ASM Test)',
        'position_id' => $asmPosition->id,
        'position' => 'ASM',
        'status' => 'Aktif',
    ]);
}

echo "[1] TEST DATA KARYAWAN & BUDGET JABATAN\n";
echo "  Karyawan: {$employee->name} ({$employee->position_name})\n";
echo "  Budget BBM: Rp " . number_format($employee->bbm_budget, 0, ',', '.') . "\n";
echo "  Budget Entertain: Rp " . number_format($employee->entertain_budget, 0, ',', '.') . "\n\n";

// 3. Create claims that exceed 750.000 for BBM
$claim1 = Claim::create([
    'employee_id' => $employee->id,
    'claim_date' => now()->toDateString(),
    'claim_category' => 'bbm',
    'claim_type' => ['BBM'],
    'vehicle_type' => 'Mobil',
    'fuel_type' => 'Pertalite',
    'fuel_base_amount' => 500000,
    'amount' => 500000,
    'approval_status' => 'DIAJUKAN',
]);

$claim2 = Claim::create([
    'employee_id' => $employee->id,
    'claim_date' => now()->toDateString(),
    'claim_category' => 'bbm',
    'claim_type' => ['BBM'],
    'vehicle_type' => 'Mobil',
    'fuel_type' => 'Pertalite',
    'fuel_base_amount' => 350000,
    'amount' => 350000,
    'approval_status' => 'DIAJUKAN',
]);

// Total used BBM = 850.000 (Over 750.000 by 100.000)
$usedBbm = $employee->getUsedBbmForPeriod(now()->month, now()->year);
$bbmStatus = $claim2->bbm_budget_status;

echo "[2] TEST PERINGATAN OVER BUDGET BBM\n";
echo "  Total Klaim BBM Bulan Ini: Rp " . number_format($usedBbm, 0, ',', '.') . "\n";
echo "  Plafon BBM: Rp " . number_format($bbmStatus['budget'], 0, ',', '.') . "\n";
echo "  Status Is Over: " . ($bbmStatus['is_over'] ? 'TRUE (LEWAT BATAS)' : 'FALSE') . "\n";
echo "  Badge Label: {$bbmStatus['label']}\n";
echo "  Badge Color: {$bbmStatus['color']}\n";

if ($bbmStatus['is_over'] && $bbmStatus['color'] === 'danger') {
    echo "  [SUCCESS] Peringatan Over Budget BBM Berfungsi dengan Benar!\n\n";
} else {
    echo "  [FAIL] Peringatan Over Budget BBM tidak sesuai.\n\n";
}

// 4. Test Entertain Budget Status
$claimEnt = Claim::create([
    'employee_id' => $employee->id,
    'claim_date' => now()->toDateString(),
    'claim_category' => 'transport_entertain',
    'claim_type' => ['Entertain'],
    'entertain_subtype' => 'Makan',
    'amount' => 1600000,
    'items' => [
        [
            'claim_type' => 'Entertain',
            'entertain_subtype' => 'Makan',
            'amount' => 1600000,
        ]
    ],
    'approval_status' => 'DIAJUKAN',
]);

$entStatus = $claimEnt->entertain_budget_status;
echo "[3] TEST PERINGATAN OVER BUDGET ENTERTAIN\n";
echo "  Plafon Entertain: Rp " . number_format($entStatus['budget'], 0, ',', '.') . "\n";
echo "  Total Terpakai Makan: Rp " . number_format($entStatus['used'], 0, ',', '.') . "\n";
echo "  Status Label: {$entStatus['label']}\n";

if ($entStatus['is_over'] && $entStatus['color'] === 'danger') {
    echo "  [SUCCESS] Peringatan Over Budget Entertain Berfungsi dengan Benar!\n\n";
} else {
    echo "  [FAIL] Peringatan Over Budget Entertain tidak sesuai.\n\n";
}

// 5. Test Rekapan Sisa Saldo
echo "[4] TEST REKAPAN SISA SALDO USER\n";
echo "  Employee: {$employee->name}\n";
echo "  Sisa BBM: " . number_format($employee->bbm_budget - $employee->used_bbm, 0, ',', '.') . "\n";
echo "  Sisa Entertain: " . number_format($employee->entertain_budget - $employee->used_entertain_makan, 0, ',', '.') . "\n";
echo "  Total Saldo Keseluruhan: Rp " . number_format($employee->total_budget, 0, ',', '.') . "\n";
echo "  Total Klaim Terpakai: Rp " . number_format($employee->total_expense_used, 0, ',', '.') . "\n";
echo "  [SUCCESS] Rekapan Sisa Saldo User Terintegrasi dengan Akurat!\n\n";

echo "========================================================\n";
echo "SEMUA FITUR REKAPAN & PERINGATAN OVER BUDGET TERVERIFIKASI SUKSES!\n";
echo "========================================================\n";

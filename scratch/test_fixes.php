<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\Claim;
use App\Models\User;
use App\Filament\Resources\PerdinClaimResource;
use Illuminate\Support\Facades\DB;

echo "=== VERIFYING FIXES ===" . PHP_EOL . PHP_EOL;

// 1. TEST EMPLOYEE isAsm(), isRgm(), isSales()
echo "--- 1. Testing Employee Role Helper Methods ---" . PHP_EOL;
$employees = Employee::all();
foreach ($employees->take(5) as $emp) {
    echo "Employee: {$emp->name} | Position: {$emp->position_name}" . PHP_EOL;
    echo " - isAsm(): " . ($emp->isAsm() ? 'YES' : 'NO') . PHP_EOL;
    echo " - isRgm(): " . ($emp->isRgm() ? 'YES' : 'NO') . PHP_EOL;
    echo " - isSales(): " . ($emp->isSales() ? 'YES' : 'NO') . PHP_EOL;
}
echo "PASSED: All role methods called without BadMethodCallException!" . PHP_EOL . PHP_EOL;

// 2. TEST SAVING EMPLOYEE WITH NULL BBM_BUDGET
echo "--- 2. Testing Saving Employee with NULL bbm_budget ---" . PHP_EOL;
DB::beginTransaction();
try {
    $emp = Employee::create([
        'name' => 'Test Employee Null Budget',
        'position' => 'Sales',
        'bbm_budget' => null,
        'perdin_budget' => null,
        'transport_budget' => null,
        'status' => 'Aktif',
    ]);

    echo "Employee created successfully with ID={$emp->id}" . PHP_EOL;
    echo " - bbm_budget: " . var_export($emp->bbm_budget, true) . PHP_EOL;
    echo " - perdin_budget: " . var_export($emp->perdin_budget, true) . PHP_EOL;
    echo " - transport_budget: " . var_export($emp->transport_budget, true) . PHP_EOL;

    // Test updating with null
    $emp->update([
        'bbm_budget' => null,
        'perdin_budget' => null,
    ]);
    $emp->refresh();
    echo "Employee updated successfully with null values!" . PHP_EOL;
    echo " - bbm_budget after update: " . var_export($emp->bbm_budget, true) . PHP_EOL;

    echo "PASSED: No Integrity constraint violation for bbm_budget!" . PHP_EOL;
} finally {
    DB::rollBack();
    echo "Transaction rolled back." . PHP_EOL;
}

// 3. TEST PERDIN ACTIONS EVALUATION
echo PHP_EOL . "--- 3. Testing Perdin Action Visibility Evaluation ---" . PHP_EOL;
$claim = Claim::where('claim_category', 'perdin')->orWhere('is_perdin', true)->first();
if (!$claim) {
    $claim = new Claim([
        'claim_type' => 'Perdin',
        'claim_category' => 'perdin',
        'is_perdin' => true,
        'approval_status' => 'DIAJUKAN',
        'employee_id' => $employees->first()?->id,
    ]);
}

$admin = User::first();
auth()->login($admin);

$applicantEmp = $claim->effective_employee;
echo "Claim ID: {$claim->id}, applicant: " . ($applicantEmp?->name ?? 'None') . PHP_EOL;
$isApplicantAsm = ($applicantEmp?->isAsm() || str_contains(strtoupper($applicantEmp?->position_name ?? ''), 'ASM'));
echo "isApplicantAsm evaluated cleanly: " . ($isApplicantAsm ? 'YES' : 'NO') . PHP_EOL;

echo PHP_EOL . "=== ALL FIXES VERIFIED SUCCESSFULLY! ===" . PHP_EOL;

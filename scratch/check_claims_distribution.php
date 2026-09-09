<?php
require 'd:/Work/Other/sales_klaim/vendor/autoload.php';
$app = require 'd:/Work/Other/sales_klaim/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Claim;
use App\Models\User;
use App\Models\Employee;

echo "=== USERS ===\n";
foreach (User::all() as $u) {
    echo "User ID: {$u->id} | Name: {$u->name} | Email: {$u->email} | Role: {$u->role_code} | EmpID: " . var_export($u->employee_id, true) . "\n";
}

echo "\n=== EMPLOYEES ===\n";
foreach (Employee::all() as $e) {
    echo "Emp ID: {$e->id} | Name: {$e->name} | Email: {$e->email} | Position: {$e->position}\n";
}

echo "\n=== TOTAL CLAIMS: " . Claim::count() . " ===\n";
echo "Claims breakdown by employee_id:\n";
$claimsByEmp = Claim::selectRaw('employee_id, count(*) as total')->groupBy('employee_id')->get();
foreach ($claimsByEmp as $c) {
    $empName = Employee::find($c->employee_id)?->name ?? 'UNKNOWN';
    echo "Employee ID {$c->employee_id} ({$empName}): {$c->total} claims\n";
}

<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\User;

$employees = Employee::all();
echo "Total employees: " . $employees->count() . "\n";
$users = User::all();
echo "Total users: " . $users->count() . "\n\n";

$unlinked = [];
foreach ($employees as $e) {
    $u = User::where('employee_id', $e->id)->orWhere('custom_id', $e->custom_id)->first();
    if (!$u) {
        $unlinked[] = "Emp #{$e->id}: {$e->name} (NIK: {$e->custom_id}) - Role: {$e->position}";
    }
}

echo "Employees without User account: " . count($unlinked) . "\n";
foreach (array_slice($unlinked, 0, 10) as $ul) {
    echo " - $ul\n";
}

<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Employee;

echo "=== USERS COLUMNS ===\n";
echo implode(", ", Schema::getColumnListing('users')) . "\n\n";

echo "=== EMPLOYEES COLUMNS ===\n";
echo implode(", ", Schema::getColumnListing('employees')) . "\n\n";

echo "=== SAMPLE USER ===\n";
$u = User::first();
echo json_encode($u, JSON_PRETTY_PRINT) . "\n\n";

echo "=== SAMPLE EMPLOYEE ===\n";
$e = Employee::first();
echo json_encode($e, JSON_PRETTY_PRINT) . "\n\n";

<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Employees: " . \App\Models\Employee::count() . "\n";
echo "Claims: " . \App\Models\Claim::count() . "\n";
echo "Users: " . \App\Models\User::count() . "\n";

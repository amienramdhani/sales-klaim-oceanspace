<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\Claim;

echo "========================================================\n";
echo "TEST PERFORMA & LOAD TIME REKAPAN SISA SALDO (NO TIMEOUT)\n";
echo "========================================================\n\n";

$start = microtime(true);

$employees = Employee::with(['positionModel', 'role', 'claims'])->get();

foreach ($employees as $e) {
    echo "Employee: {$e->name}\n";
    echo "  BBM Used (Month 8): " . $e->getUsedBbmForPeriod(8, 2026) . "\n";
    echo "  Entertain Used (Month 8): " . $e->getUsedEntertainForPeriod(8, 2026) . "\n";
    echo "  Perdin Used (Month 8): " . $e->getUsedPerdinForPeriod(8, 2026) . "\n";
    echo "  Transport Used (Month 8): " . $e->getUsedTransportForPeriod(8, 2026) . "\n";
    echo "  Total Used (Month 8): " . $e->getTotalExpenseUsedForPeriod(8, 2026) . "\n";
    echo "  Total Budget: {$e->total_budget}\n";
    echo "  Remaining: " . ($e->total_budget - $e->getTotalExpenseUsedForPeriod(8, 2026)) . "\n";
}

$elapsed = round(microtime(true) - $start, 4);

echo "\n========================================================\n";
echo "[SUCCESS] Total Execution Time: {$elapsed} seconds!\n";
echo "========================================================\n";

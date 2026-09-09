<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Filament\Resources\PerdinClaimResource;
use Filament\Forms\Components\Component;

echo "=== TEST 3: Testing 1-day PP Perdin (0 nights) ===\n";

$stateData = [
    'employee_id' => 4,
    'claim_date' => '2026-09-10',
    'perdin_return_date' => '2026-09-10', // 1 day, 0 nights
    'days_count' => 1,
    'nights_count' => 0,
    'toll_cost' => 100000,
    'fuel_cost' => 150000,
    'car_rental_cost' => 0,
];

$get = new class($stateData) extends Filament\Forms\Get {
    private $data;
    public function __construct(&$data) { $this->data = &$data; }
    public function __invoke(Component|string $path = '', bool $isAbsolute = false): mixed { 
        $p = is_object($path) ? $path->getName() : (string)$path;
        return $this->data[$p] ?? null; 
    }
};

$set = new class($stateData) extends Filament\Forms\Set {
    private $data;
    public function __construct(&$data) { $this->data = &$data; }
    public function __invoke(Component|string $path, mixed $value, bool $isAbsolute = false): mixed { 
        $p = is_object($path) ? $path->getName() : (string)$path;
        $this->data[$p] = $value; 
        echo "   -> Set {$p} = {$value}\n";
        return $value;
    }
};

PerdinClaimResource::recalculatePerdin($get, $set, 1, 0);

$expectedMeal = 1 * 35000;      // 35,000
$expectedLodging = 0 * 250000;  // 0
$expectedTotal = $expectedMeal + $expectedLodging + 100000 + 150000; // 285,000

echo "Recalculated values:\n";
echo " - days_count: " . $stateData['days_count'] . " (Expected: 1)\n";
echo " - nights_count: " . $stateData['nights_count'] . " (Expected: 0)\n";
echo " - meal_allowance: " . $stateData['meal_allowance'] . " (Expected: {$expectedMeal})\n";
echo " - lodging_allowance: " . $stateData['lodging_allowance'] . " (Expected: {$expectedLodging})\n";
echo " - total amount: " . $stateData['amount'] . " (Expected: {$expectedTotal})\n";

if ($stateData['meal_allowance'] == $expectedMeal && 
    $stateData['lodging_allowance'] == $expectedLodging && 
    $stateData['amount'] == $expectedTotal) {
    echo " [PASS] 1-day PP logic passes perfectly!\n";
} else {
    echo " [FAIL] 1-day PP did not match expected values.\n";
}

<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Models\User;
use App\Models\Employee;
use App\Filament\Resources\PerdinClaimResource;
use Filament\Forms\Components\Component;

echo "=== TEST 2: Testing recalculatePerdin logic ===\n";
$emp = Employee::find(4); // Bima (meal: 35000, lodging: 250000)
echo "Employee #4: {$emp->name}, meal_rate: {$emp->perdin_meal_allowance}, lodging_rate: {$emp->perdin_lodging_allowance}\n";

// Mock Get & Set
$stateData = [
    'employee_id' => 4,
    'claim_date' => '2026-09-10',
    'perdin_return_date' => '2026-09-12', // 3 days, 2 nights
    'days_count' => 3,
    'nights_count' => 2,
    'toll_cost' => 150000,
    'fuel_cost' => 300000,
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

PerdinClaimResource::recalculatePerdin($get, $set, 3, 2);

$expectedMeal = 3 * 35000;      // 105,000
$expectedLodging = 2 * 250000;  // 500,000
$expectedTotal = $expectedMeal + $expectedLodging + 150000 + 300000; // 1,055,000

echo "Recalculated values:\n";
echo " - days_count: " . $stateData['days_count'] . " (Expected: 3)\n";
echo " - nights_count: " . $stateData['nights_count'] . " (Expected: 2)\n";
echo " - meal_allowance: " . $stateData['meal_allowance'] . " (Expected: {$expectedMeal})\n";
echo " - lodging_allowance: " . $stateData['lodging_allowance'] . " (Expected: {$expectedLodging})\n";
echo " - total amount: " . $stateData['amount'] . " (Expected: {$expectedTotal})\n";

if ($stateData['meal_allowance'] == $expectedMeal && 
    $stateData['lodging_allowance'] == $expectedLodging && 
    $stateData['amount'] == $expectedTotal) {
    echo " [PASS] Recalculation logic perfectly matches duration and allowances!\n";
} else {
    echo " [FAIL] Recalculation did not match expected values.\n";
}

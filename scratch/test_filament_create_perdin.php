<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Claim;
use App\Filament\Resources\PerdinClaimResource\Pages\CreatePerdinClaim;

$sales = User::where('email', 'bima@msi.com')->first();
auth()->login($sales);

echo "Logged in as {$sales->name} (User #{$sales->id})\n";

// Test simulate payload that previously failed with null lodging_allowance and toll_cost
$payload = [
    'user_id' => $sales->id,
    'employee_id' => 4,
    'branch_id' => 10,
    'homebase' => 'JAMBI',
    'claim_date' => '2026-09-06',
    'perdin_return_date' => '2026-09-07',
    'destination_city' => 'PADANG',
    'distance_km' => 400,
    'days_count' => 2,
    'nights_count' => 1,
    'purpose' => 'MARKET VISIT',
    'meal_allowance' => 70000,
    'lodging_allowance' => null, // Left empty by user in form
    'toll_cost' => null,        // Left empty by user in form
    'fuel_cost' => 500000,
    'car_rental_cost' => null,
    'amount' => 570000,
    'photos' => [],
    'approval_status' => 'DIAJUKAN',
];

// Test CreatePerdinClaim mutateFormDataBeforeCreate
$page = new CreatePerdinClaim();
$reflection = new ReflectionClass($page);
$method = $reflection->getMethod('mutateFormDataBeforeCreate');
$method->setAccessible(true);

$mutated = $method->invoke($page, $payload);

echo "Mutated Data before insert:\n";
echo " - lodging_allowance: " . var_export($mutated['lodging_allowance'], true) . "\n";
echo " - toll_cost: " . var_export($mutated['toll_cost'], true) . "\n";
echo " - meal_allowance: " . var_export($mutated['meal_allowance'], true) . "\n";
echo " - days_count: " . var_export($mutated['days_count'], true) . "\n";
echo " - amount: " . var_export($mutated['amount'], true) . "\n";

$claim = Claim::create($mutated);
echo " [SUCCESS] Claim created successfully in database with ID: {$claim->id}\n";
echo " - Stored Lodging: {$claim->lodging_allowance}\n";
echo " - Stored Toll: {$claim->toll_cost}\n";
echo " - Stored Total Amount: {$claim->amount}\n";

$claim->delete();
echo " [SUCCESS] Cleaned up created claim.\n";

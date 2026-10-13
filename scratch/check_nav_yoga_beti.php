<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::find(39); // Yoga
auth()->login($user);

echo "Checking resources for Yoga (ID 39):\n";
$resources = [
    'PerdinClaimResource' => App\Filament\Resources\PerdinClaimResource::canViewAny(),
    'VehicleServiceClaimResource' => App\Filament\Resources\VehicleServiceClaimResource::canViewAny(),
    'TransportEntertainClaimResource' => App\Filament\Resources\TransportEntertainClaimResource::canViewAny(),
    'BbmClaimResource' => App\Filament\Resources\BbmClaimResource::canViewAny(),
    'FinanceNonBbmClaimResource' => App\Filament\Resources\FinanceNonBbmClaimResource::canViewAny(),
    'FinanceBbmClaimResource' => App\Filament\Resources\FinanceBbmClaimResource::canViewAny(),
    'YogaClaimApprovalResource' => App\Filament\Resources\YogaClaimApprovalResource::canViewAny(),
    'NurbaitiClaimApprovalResource' => App\Filament\Resources\NurbaitiClaimApprovalResource::canViewAny(),
];

foreach ($resources as $res => $can) {
    echo "$res: " . ($can ? 'YES' : 'NO') . "\n";
}

$user40 = App\Models\User::find(40); // Nurbaiti
auth()->login($user40);

echo "\nChecking resources for Nurbaiti (ID 40):\n";
foreach ($resources as $res => $can) {
    $class = "App\\Filament\\Resources\\$res";
    echo "$res: " . ($class::canViewAny() ? 'YES' : 'NO') . "\n";
}

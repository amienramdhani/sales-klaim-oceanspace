<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Filament\Resources\BbmClaimResource;
use App\Filament\Resources\TransportEntertainClaimResource;
use App\Filament\Resources\BbmBeritaAcaraResource;
use App\Filament\Resources\PerdinClaimResource;
use App\Filament\Resources\VehicleServiceClaimResource;

$sales = User::where('email', 'bima@msi.com')->first();
auth()->login($sales);

echo "Testing queries as Sales user: {$sales->name} ({$sales->role_code})\n";

$resources = [
    'BBM' => BbmClaimResource::class,
    'Transport & Entertain' => TransportEntertainClaimResource::class,
    'BA BBM' => BbmBeritaAcaraResource::class,
    'Perdin' => PerdinClaimResource::class,
    'Service Motor' => VehicleServiceClaimResource::class,
];

foreach ($resources as $name => $class) {
    try {
        $count = $class::getEloquentQuery()->count();
        $canCreate = $class::canCreate();
        echo " - [PASS] {$name}: query count = {$count}, canCreate = " . ($canCreate ? 'YES' : 'NO') . "\n";
    } catch (\Throwable $e) {
        echo " - [FAIL] {$name}: " . $e->getMessage() . "\n";
    }
}

echo "All Sales queries completed!\n";

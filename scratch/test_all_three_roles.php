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

$roles = [
    'SALES' => 'bima@msi.com',
    'ASM' => 'asm@salesklaim.com',
    'RGM' => 'rgm@salesklaim.com',
];

$resources = [
    'BBM' => BbmClaimResource::class,
    'Transport & Entertain' => TransportEntertainClaimResource::class,
    'BA BBM' => BbmBeritaAcaraResource::class,
    'Perdin' => PerdinClaimResource::class,
    'Service Motor' => VehicleServiceClaimResource::class,
];

foreach ($roles as $roleLabel => $email) {
    $user = User::where('email', $email)->first();
    auth()->login($user);
    echo "=== {$roleLabel}: {$user->name} ({$user->role_code}) ===\n";
    foreach ($resources as $name => $class) {
        try {
            $count = $class::getEloquentQuery()->count();
            $canView = $class::canViewAny();
            $canCreate = $class::canCreate();
            echo "  - {$name}: canView=" . ($canView ? 'YES' : 'NO') . ", canCreate=" . ($canCreate ? 'YES' : 'NO') . ", count={$count}\n";
        } catch (\Throwable $e) {
            echo "  - [ERROR] {$name}: " . $e->getMessage() . "\n";
        }
    }
}

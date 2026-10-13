<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rolesToCheck = [
    'JEJEN' => 'jejen@msi.com',
    'FINANCE' => 'finance@salesklaim.com',
    'SALES' => 'bima@msi.com',
    'ASM' => 'asm@salesklaim.com',
    'RGM' => 'hendra@msi.com',
];

$resources = [
    'BbmClaim' => App\Filament\Resources\BbmClaimResource::class,
    'TransportEntertainClaim' => App\Filament\Resources\TransportEntertainClaimResource::class,
    'PerdinClaim' => App\Filament\Resources\PerdinClaimResource::class,
    'VehicleServiceClaim' => App\Filament\Resources\VehicleServiceClaimResource::class,
    'BbmBeritaAcara' => App\Filament\Resources\BbmBeritaAcaraResource::class,
    'Claim (Semua)' => App\Filament\Resources\ClaimResource::class,
    'AsmApproval' => App\Filament\Resources\AsmClaimApprovalResource::class,
    'RgmApproval' => App\Filament\Resources\RgmClaimApprovalResource::class,
    'JejenApproval' => App\Filament\Resources\JejenClaimApprovalResource::class,
    'AdminApproval' => App\Filament\Resources\AdminClaimApprovalResource::class,
    'FinanceBbm' => App\Filament\Resources\FinanceBbmClaimResource::class,
    'FinanceNonBbm' => App\Filament\Resources\FinanceNonBbmClaimResource::class,
    'AttendanceDoc' => App\Filament\Resources\AttendanceDocResource::class,
    'UserExpenseReport' => App\Filament\Resources\UserExpenseReportResource::class,
    'BbmExpenseReport' => App\Filament\Resources\BbmExpenseReportResource::class,
    'EntertainExpenseReport' => App\Filament\Resources\EntertainExpenseReportResource::class,
];

foreach ($rolesToCheck as $roleName => $email) {
    $user = App\Models\User::where('email', $email)->first();
    if (!$user) {
        echo "User {$email} not found!\n";
        continue;
    }
    auth()->login($user);
    echo "=== CAN VIEW ANY FOR {$roleName} ({$user->name}) ===\n";
    foreach ($resources as $name => $class) {
        $can = $class::canViewAny();
        if ($can) {
            echo "  [VISIBLE] {$name}\n";
        }
    }
    echo "\n";
}

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
use App\Filament\Resources\ClaimResource;
use App\Filament\Resources\FinanceClaimResource;
use App\Filament\Resources\BbmExpenseReportResource;
use App\Filament\Resources\EntertainExpenseReportResource;
use App\Filament\Resources\AttendanceDocResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\UserResource;

$resources = [
    'BBM' => BbmClaimResource::class,
    'Transport & Entertain' => TransportEntertainClaimResource::class,
    'BA BBM' => BbmBeritaAcaraResource::class,
    'Perdin' => PerdinClaimResource::class,
    'Service Motor' => VehicleServiceClaimResource::class,
    'Semua Klaim (ClaimResource)' => ClaimResource::class,
    'Finance Klaim' => FinanceClaimResource::class,
    'Rekapan BBM' => BbmExpenseReportResource::class,
    'Rekapan Entertain' => EntertainExpenseReportResource::class,
    'Attendance Doc' => AttendanceDocResource::class,
    'Employee' => EmployeeResource::class,
    'User' => UserResource::class,
];

$testUsers = [
    'RGM' => User::where('email', 'rgm@salesklaim.com')->first(),
    'ASM' => User::where('email', 'asm@salesklaim.com')->first(),
    'SALES (bima)' => User::where('email', 'bima@msi.com')->first(),
];

$result = [];
foreach ($testUsers as $roleKey => $user) {
    if (!$user) {
        $result[$roleKey] = 'User not found';
        continue;
    }
    auth()->login($user);
    $visible = [];
    foreach ($resources as $name => $class) {
        $can = $class::canViewAny();
        $visible[$name] = $can ? 'YES' : 'NO';
    }
    $result[$roleKey . ' (' . $user->name . ' - Role: ' . $user->role_code . ')'] = $visible;
}

echo json_encode($result, JSON_PRETTY_PRINT);

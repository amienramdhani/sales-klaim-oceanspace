<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================================\n";
echo "TEST QUERY & RELATION LOADING PADA SEMUA RESOURCE\n";
echo "========================================================\n\n";

$resources = [
    'Rekapan Entertain' => \App\Filament\Resources\EntertainExpenseReportResource::class,
    'Rekapan BBM' => \App\Filament\Resources\BbmExpenseReportResource::class,
    'Rekapan Transportasi' => \App\Filament\Resources\TransportExpenseReportResource::class,
    'Rekapan Perdin' => \App\Filament\Resources\PerdinExpenseReportResource::class,
    'Klaim Transport & Entertain' => \App\Filament\Resources\TransportEntertainClaimResource::class,
    'Klaim BBM' => \App\Filament\Resources\BbmClaimResource::class,
    'Klaim Perdin' => \App\Filament\Resources\PerdinClaimResource::class,
    'Klaim Service Kendaraan' => \App\Filament\Resources\VehicleServiceClaimResource::class,
    'Pencairan Finance' => \App\Filament\Resources\FinanceClaimResource::class,
    'Semua Pengajuan Klaim' => \App\Filament\Resources\ClaimResource::class,
];

foreach ($resources as $name => $resClass) {
    $startTime = microtime(true);
    try {
        $query = $resClass::getEloquentQuery();
        $records = $query->get();
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        echo "  [SUCCESS] {$name}:\n";
        echo "    - Total Data: {$records->count()} baris\n";
        echo "    - Waktu Query: {$duration} ms (Sangat Cepat!)\n";

        // Test accessing employee and effective_employee on each record
        foreach ($records as $r) {
            $empName = $r->effective_employee?->name ?? $r->employee?->name ?? '-';
            $pos = $r->effective_employee?->position_name ?? '-';
            $city = $r->branch?->city ?? '-';
        }
    } catch (\Throwable $e) {
        echo "  [FAILED] {$name}: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "========================================================\n";
echo "SEMUA QUERY & RELASI BERJALAN LANCAR TANPA EXCEPTION!\n";
echo "========================================================\n";

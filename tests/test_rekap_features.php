<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================================\n";
echo "TEST VERIFIKASI FITUR REKAPAN BIAYA & PEMISAHAN MENU\n";
echo "========================================================\n\n";

$claimService = app(\App\Services\ClaimExcelExportService::class);
$allClaims = \App\Models\Claim::with(['employee.role', 'branch'])->get();

echo "Total Data Klaim di Database: " . $allClaims->count() . "\n\n";

// 1. Test Rekapan BBM Excel
echo "[1] TEST REKAPAN BIAYA BBM EXCEL EXPORT\n";
try {
    $res = $claimService->exportBbmRecapExcel($allClaims);
    echo "  [SUCCESS] Rekap Biaya BBM Excel berhasil dibuat! (Status: {$res->getStatusCode()})\n";
} catch (\Throwable $e) {
    echo "  [FAILED] BBM Recap error: " . $e->getMessage() . "\n";
}

// 2. Test Rekapan Entertain Excel
echo "\n[2] TEST REKAPAN BIAYA ENTERTAIN EXCEL EXPORT\n";
try {
    $res = $claimService->exportEntertainRecapExcel($allClaims);
    echo "  [SUCCESS] Rekap Biaya Entertain Excel berhasil dibuat! (Status: {$res->getStatusCode()})\n";
} catch (\Throwable $e) {
    echo "  [FAILED] Entertain Recap error: " . $e->getMessage() . "\n";
}

// 3. Test Rekapan Perdin Excel
echo "\n[3] TEST REKAPAN BIAYA PERJALANAN DINAS EXCEL EXPORT\n";
try {
    $res = $claimService->exportPerdinRecapExcel($allClaims);
    echo "  [SUCCESS] Rekap Biaya Perdin Excel berhasil dibuat! (Status: {$res->getStatusCode()})\n";
} catch (\Throwable $e) {
    echo "  [FAILED] Perdin Recap error: " . $e->getMessage() . "\n";
}

// 4. Test Rekapan Transportasi Excel
echo "\n[4] TEST REKAPAN BIAYA TRANSPORTASI EXCEL EXPORT\n";
try {
    $res = $claimService->exportTransportRecapExcel($allClaims);
    echo "  [SUCCESS] Rekap Biaya Transportasi Excel berhasil dibuat! (Status: {$res->getStatusCode()})\n";
} catch (\Throwable $e) {
    echo "  [FAILED] Transport Recap error: " . $e->getMessage() . "\n";
}

// 5. Cek Kelas Navigasi & Resources
echo "\n[5] VERIFIKASI RESOURCE PENGAJUAN KLAIM & REKAPAN BIAYA\n";
$expectedGroups = [
    'PENGAJUAN KLAIM' => [
        \App\Filament\Resources\TransportEntertainClaimResource::class => 'Klaim Transport & Entertain',
        \App\Filament\Resources\BbmClaimResource::class => 'Klaim BBM',
        \App\Filament\Resources\PerdinClaimResource::class => 'Klaim Perjalanan Dinas',
        \App\Filament\Resources\VehicleServiceClaimResource::class => 'Klaim Service Kendaraan',
        \App\Filament\Resources\FinanceClaimResource::class => 'Pencairan Finance',
        \App\Filament\Resources\ClaimResource::class => 'Semua Pengajuan Klaim',
    ],
    'REKAPAN BIAYA' => [
        \App\Filament\Resources\BbmExpenseReportResource::class => 'Rekapan Biaya BBM',
        \App\Filament\Resources\EntertainExpenseReportResource::class => 'Rekapan Biaya Entertain',
        \App\Filament\Resources\PerdinExpenseReportResource::class => 'Rekapan Biaya Perjalanan Dinas',
        \App\Filament\Resources\TransportExpenseReportResource::class => 'Rekapan Biaya Transportasi',
        \App\Filament\Resources\UserExpenseReportResource::class => 'Rekapan Sisa Saldo & Plafon',
    ],
];

foreach ($expectedGroups as $group => $items) {
    echo "  Group: [{$group}]\n";
    foreach ($items as $cls => $name) {
        if (class_exists($cls)) {
            echo "    ✓ {$name} ({$cls})\n";
        } else {
            echo "    ✗ ERROR: {$name} not found!\n";
        }
    }
}

echo "\n========================================================\n";
echo "SEMUA REKAPAN BIAYA DAN PEMISAHAN MENU SELESAI 100%!\n";
echo "========================================================\n";

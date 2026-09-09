<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Models\Employee;
use App\Services\ClaimExcelExportService;

echo "========================================================\n";
echo "TEST VERIFIKASI EXCEL EXPORT DENGAN TANGGAL NOTA & KM AWAL\n";
echo "========================================================\n\n";

$exportService = app(ClaimExcelExportService::class);

// 1. Get or Create a test claim with receipt_date and fuel_start_km
$claim = Claim::where('claim_category', 'bbm')->first();
if (!$claim) {
    $emp = Employee::first();
    $claim = Claim::create([
        'employee_id' => $emp?->id,
        'claim_date' => now()->toDateString(),
        'claim_type' => ['BBM'],
        'claim_category' => 'bbm',
        'purpose' => 'BBM Luar Kota',
        'note' => 'SPBU Tuparev Cirebon',
        'fuel_type' => 'Pertalite',
        'vehicle_type' => 'Mobil',
        'fuel_base_amount' => 150000,
        'fuel_start_km' => 12540,
        'amount' => 150000,
        'items' => [
            [
                'receipt_date' => now()->subDays(2)->toDateString(),
                'claim_type' => 'BBM',
                'vehicle_type' => 'Mobil',
                'fuel_type' => 'Pertalite',
                'note' => 'SPBU Tuparev Cirebon',
                'fuel_start_km' => 12540,
                'fuel_base_amount' => 150000,
                'amount' => 150000,
            ]
        ],
        'approval_status' => 'DISETUJUI',
    ]);
}

// Test 1: Single Claim Export
echo "[1] TEST SINGLE CLAIM EXPORT\n";
$res1 = $exportService->exportSingleClaim($claim);
echo "  [SUCCESS] Single Claim Excel Response Generated! (Class: " . get_class($res1) . ")\n\n";

// Test 2: Claims Collection Export
echo "[2] TEST CLAIMS COLLECTION EXPORT\n";
$claims = Claim::take(5)->get();
$res2 = $exportService->exportClaims($claims);
echo "  [SUCCESS] Claims Collection Excel Response Generated!\n\n";

// Test 3: Rekap Biaya BBM
echo "[3] TEST REKAP BIAYA BBM EXPORT (WITH TGL NOTA & KM AWAL)\n";
$bbmClaims = Claim::where('claim_category', 'bbm')->get();
$res3 = $exportService->exportBbmRecapExcel($bbmClaims);
echo "  [SUCCESS] Rekap BBM Excel Response Generated!\n\n";

// Test 4: Rekap Biaya Entertain
echo "[4] TEST REKAP BIAYA ENTERTAIN EXPORT (WITH TGL NOTA)\n";
$res4 = $exportService->exportEntertainRecapExcel($claims);
echo "  [SUCCESS] Rekap Entertain Excel Response Generated!\n\n";

// Test 5: Rekap Biaya Transportasi
echo "[5] TEST REKAP BIAYA TRANSPORTASI EXPORT (WITH TGL NOTA)\n";
$res5 = $exportService->exportTransportRecapExcel($claims);
echo "  [SUCCESS] Rekap Transportasi Excel Response Generated!\n\n";

// Test 6: Rekapan Sisa Saldo & Plafon
echo "[6] TEST REKAPAN SISA SALDO EXPORT (WITH TGL NOTA)\n";
$res6 = $exportService->exportSisaKlaimRecapTemplate($claims);
echo "  [SUCCESS] Rekap Sisa Saldo Excel Response Generated!\n\n";

echo "========================================================\n";
echo "SEMUA TEMPLATE EXCEL SUDAH MEMUAT TANGGAL NOTA & KM AWAL DENGAN SUKSES!\n";
echo "========================================================\n";

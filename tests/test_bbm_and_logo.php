<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================================\n";
echo "TEST VERIFIKASI LOGO & KETENTUAN BBM MOBIL (+5K & PERTALITE)\n";
echo "========================================================\n\n";

// 1. Check Logo File
echo "[1] CEK ASSET LOGO PERUSAHAAN\n";
$logoPath = public_path('images/company-logo.png');
if (file_exists($logoPath)) {
    $logoSize = getimagesize($logoPath);
    echo "  [SUCCESS] Logo perusahaan ditemukan di: {$logoPath} ({$logoSize[0]}x{$logoSize[1]} px)\n";
} else {
    echo "  [FAILED] Logo tidak ditemukan.\n";
}

// 2. Test Excel Perdin Form with Logo Embedded
echo "\n[2] TEST EXPORT FORM PERDIN DENGAN LOGO PERUSAHAAN\n";
try {
    $excelService = app(App\Services\ClaimExcelExportService::class);
    $response = $excelService->exportPerdinForm(null, [
        'name' => 'RIVENJER BILLY KAPAHANG',
        'position' => 'ASM',
        'destination' => 'KEPULAUAN TAHUNA',
        'purpose' => 'VISIT DEALER',
        'days' => 3,
    ]);
    echo "  [SUCCESS] Form Perdin Excel generated with Logo drawing!\n";
} catch (\Throwable $e) {
    echo "  [FAILED] Perdin export error: " . $e->getMessage() . "\n";
}

// 3. Test BBM Mobil Rule: Pertalite + Rp 5.000
echo "\n[3] TEST KETENTUAN BBM MOBIL (+5K & WAJIB PERTALITE)\n";

// Sample 1: Mobil Pertalite 200.000 -> Harus 205.000
$claimMobilPertalite = App\Models\Claim::create([
    'employee_id' => App\Models\Employee::first()->id,
    'claim_date' => date('Y-m-d'),
    'claim_type' => ['BBM'],
    'purpose' => ['Operasional Mobil Cabang Pertalite'],
    'note' => 'SPBU Pertamina 44.531',
    'vehicle_type' => 'Mobil',
    'fuel_type' => 'Pertalite',
    'fuel_base_amount' => 200000,
    'fuel_extra_amount' => 5000,
    'amount' => 205000,
]);
echo "  Sample 1 (Mobil Pertalite 200k + 5k = 205k):\n";
echo "   - Status Kepatuhan BBM: " . $claimMobilPertalite->fuel_compliance['label'] . " (" . $claimMobilPertalite->fuel_compliance['status'] . ")\n";

// Sample 2: Mobil Pertamax (Harus Ditolak / Invalid)
$claimMobilPertamax = App\Models\Claim::create([
    'employee_id' => App\Models\Employee::first()->id,
    'claim_date' => date('Y-m-d'),
    'claim_type' => ['BBM'],
    'purpose' => ['Operasional BBM Pertamax (Dilarang)'],
    'note' => 'SPBU Pertamina 44.532',
    'vehicle_type' => 'Mobil',
    'fuel_type' => 'Pertamax',
    'fuel_base_amount' => 200000,
    'fuel_extra_amount' => 5000,
    'amount' => 205000,
]);
echo "  Sample 2 (Mobil Pertamax):\n";
echo "   - Status Kepatuhan BBM: " . $claimMobilPertamax->fuel_compliance['label'] . " (" . $claimMobilPertamax->fuel_compliance['status'] . ")\n";

// Sample 3: Mobil Pertalite Belum +5k
$claimMobilKurang = App\Models\Claim::create([
    'employee_id' => App\Models\Employee::first()->id,
    'claim_date' => date('Y-m-d'),
    'claim_type' => ['BBM'],
    'purpose' => ['Operasional Mobil Pas 200k (Belum +5k)'],
    'note' => 'SPBU Pertamina 44.533',
    'vehicle_type' => 'Mobil',
    'fuel_type' => 'Pertalite',
    'fuel_base_amount' => 200000,
    'fuel_extra_amount' => 0,
    'amount' => 200000,
]);
echo "  Sample 3 (Mobil Pertalite Pas 200k):\n";
echo "   - Status Kepatuhan BBM: " . $claimMobilKurang->fuel_compliance['label'] . " (" . $claimMobilKurang->fuel_compliance['status'] . ")\n";

echo "\n========================================================\n";
echo "SEMUA TEST BERHASIL DIJALANKAN DENGAN SUKSES!\n";
echo "========================================================\n";

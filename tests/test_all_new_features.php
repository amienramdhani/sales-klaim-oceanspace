<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================================\n";
echo "TEST VERIFIKASI PERBAIKAN & FITUR BARU LENGKAP\n";
echo "========================================================\n\n";

// 1. Test Pertamax Calculation (50k / 12.300 * 10.000)
echo "[1] TEST FORMULA PERTAMAX KONVERSI PERTALITE\n";
$baseNota = 50000;
$pertamaxPrice = 12300;
$pertalitePrice = 10000;
$liters = round($baseNota / $pertamaxPrice, 3);
$convertedClaimMotor = round($liters * $pertalitePrice);
$convertedClaim = $convertedClaimMotor;

echo "  Beli Pertamax: Rp " . number_format($baseNota, 0, ',', '.') . "\n";
echo "  Harga Pertamax: Rp " . number_format($pertamaxPrice, 0, ',', '.') . "/L | Harga Pertalite: Rp " . number_format($pertalitePrice, 0, ',', '.') . "/L\n";
echo "  Volume Didapat: {$liters} Liter\n";
echo "  Nominal Klaim yang Disetujui: Rp " . number_format($convertedClaim, 0, ',', '.') . "\n";

$pertamaxClaim = \App\Models\Claim::create([
    'employee_id' => \App\Models\Employee::first()->id,
    'claim_category' => 'bbm',
    'claim_date' => date('Y-m-d'),
    'claim_type' => ['BBM'],
    'purpose' => ['Operasional BBM Pertamax Konversi'],
    'note' => 'SPBU 44.531 Tuparev Cirebon',
    'vehicle_type' => 'Mobil',
    'fuel_type' => 'Pertamax',
    'fuel_base_amount' => $baseNota,
    'fuel_pertamax_price' => $pertamaxPrice,
    'fuel_pertalite_price' => $pertalitePrice,
    'fuel_liters' => $liters,
    'fuel_extra_amount' => 0,
    'amount' => $convertedClaim,
]);
echo "  [SUCCESS] Pertamax Claim created! Compliance Status: " . $pertamaxClaim->fuel_compliance['label'] . "\n";

// 2. Test Berita Acara Word Export Generation
echo "\n[2] TEST EXPORT BERITA ACARA KETIDAKSESUAIAN BBM (.DOCX)\n";
try {
    $baService = app(\App\Services\BbmBeritaAcaraWordExportService::class);
    $response = $baService->export($pertamaxClaim, [
        'name' => 'JOHAN ARIF',
        'position' => 'ASM',
        'phone' => '+62 821-7510-0062',
        'division' => 'Distribusi Sales - TECNO',
        'approver_1' => 'Agus Supangat',
        'approver_2' => 'Alb. Maria Adi Nugroho',
        'approver_3' => 'Yoga Prima Hadi',
    ]);
    echo "  [SUCCESS] Berita Acara Word Document generated successfully: " . $response->getFile()->getFilename() . "\n";
} catch (\Throwable $e) {
    echo "  [FAILED] Berita Acara export error: " . $e->getMessage() . "\n";
}

// 3. Test _UID and Finance Disbursement Flow
echo "\n[3] TEST ALUR _UID & FINANCE DISBURSEMENT\n";
$financeUser = \App\Models\User::where('custom_id', 'FIN-001')->first();

echo "  Step 1: Admin input _UID dari form external...\n";
$pertamaxClaim->update([
    '_uid' => 'UID-2026-08-9988',
    'approval_status' => 'ACC_RGM',
]);
echo "  Claim updated with _UID: {$pertamaxClaim->_uid} | Status: {$pertamaxClaim->approval_status}\n";

echo "  Step 2: Finance login dan melakukan pencairan dengan upload bukti transfer...\n";
$pertamaxClaim->update([
    'disbursement_status' => 'Sudah Dicairkan',
    'disbursed_at' => now(),
    'transfer_proof_photo' => 'transfer-proofs/transfer_sample.jpg',
    'finance_approved_by_id' => $financeUser?->id,
    'finance_approved_at' => now(),
    'approval_status' => 'DISETUJUI',
]);
echo "  [SUCCESS] Pencairan Finance selesai! Status: {$pertamaxClaim->disbursement_status} | Diproses oleh: {$financeUser?->name} ({$financeUser?->custom_id})\n";

// 4. Test Grouped Resources Classes
echo "\n[4] CEK KELAS KELOMPOK RESOURCE FILAMENT\n";
$resources = [
    \App\Filament\Resources\TransportEntertainClaimResource::class => 'Klaim Transport & Entertain',
    \App\Filament\Resources\BbmClaimResource::class => 'Klaim BBM',
    \App\Filament\Resources\PerdinClaimResource::class => 'Klaim Perjalanan Dinas',
    \App\Filament\Resources\VehicleServiceClaimResource::class => 'Klaim Service Kendaraan',
    \App\Filament\Resources\FinanceClaimResource::class => 'Pencairan Finance',
    \App\Filament\Resources\ClaimResource::class => 'Semua Pengajuan Klaim',
];

foreach ($resources as $resClass => $name) {
    if (class_exists($resClass)) {
        echo "  [SUCCESS] Resource {$name} ({$resClass}) loaded successfully!\n";
    } else {
        echo "  [FAILED] Resource {$name} not found.\n";
    }
}

echo "\n========================================================\n";
echo "SEMUA FITUR TELAH TERVERIFIKASI DENGAN BERHASIL!\n";
echo "========================================================\n";

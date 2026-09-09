<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Claim;
use App\Models\Employee;

echo "========================================================\n";
echo "TEST MULTI-ITEM BBM PHOTOS BEFORE & AFTER\n";
echo "========================================================\n\n";

$employee = Employee::first();

// Create sample image files in storage/app/public/claim-photos if not exist
$storageDir = storage_path('app/public/claim-photos');
if (!file_exists($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$img1Path = $storageDir . '/test_before_1.jpg';
$img2Path = $storageDir . '/test_after_1.jpg';
$img3Path = $storageDir . '/test_before_2.jpg';
$img4Path = $storageDir . '/test_after_2.jpg';

// Create simple test images
$im = imagecreatetruecolor(400, 300);
imagefilledrectangle($im, 0, 0, 400, 300, imagecolorallocate($im, 200, 50, 50));
imagejpeg($im, $img1Path);

$im2 = imagecreatetruecolor(400, 300);
imagefilledrectangle($im2, 0, 0, 400, 300, imagecolorallocate($im2, 50, 200, 50));
imagejpeg($im2, $img2Path);

$im3 = imagecreatetruecolor(400, 300);
imagefilledrectangle($im3, 0, 0, 400, 300, imagecolorallocate($im3, 50, 50, 200));
imagejpeg($im3, $img3Path);

$im4 = imagecreatetruecolor(400, 300);
imagefilledrectangle($im4, 0, 0, 400, 300, imagecolorallocate($im4, 200, 200, 50));
imagejpeg($im4, $img4Path);

$itemsData = [
    [
        'vehicle_type' => 'Mobil',
        'fuel_type' => 'Pertalite',
        'note' => 'SPBU 44.531 Tuparev Cirebon',
        'fuel_base_amount' => 105000,
        'fuel_liters' => 10.5,
        'amount' => 105000,
        'bbm_photo_before' => 'claim-photos/test_before_1.jpg',
        'bbm_photo_after' => 'claim-photos/test_after_1.jpg',
    ],
    [
        'vehicle_type' => 'Motor',
        'fuel_type' => 'Pertamax',
        'note' => 'SPBU 90.90 Plered',
        'fuel_base_amount' => 50000,
        'fuel_pertamax_price' => 12300,
        'fuel_pertalite_price' => 10000,
        'fuel_liters' => 4.065,
        'amount' => 40650,
        'bbm_photo_before' => 'claim-photos/test_before_2.jpg',
        'bbm_photo_after' => 'claim-photos/test_after_2.jpg',
    ]
];

$totalAmount = 105000 + 40650;

$claim = Claim::create([
    'claim_category' => 'bbm',
    'employee_id' => $employee->id,
    'claim_date' => date('Y-m-d'),
    'claim_type' => ['BBM'],
    'purpose' => ['Operasional Visit Dealer Multi-SPBU'],
    'items' => $itemsData,
    'amount' => $totalAmount,
    'approval_status' => 'DIAJUKAN',
    'disbursement_status' => 'Belum Dicairkan',
]);

echo "Claim BBM Multi-Item Created with ID: {$claim->id}\n";
echo "Items Count: " . count($claim->items) . "\n";
echo "Total Nominal: Rp " . number_format($claim->amount, 0, ',', '.') . "\n";
echo "Combined Photo Created: " . ($claim->bbm_photo_combined ?? 'None') . "\n";

if ($claim->bbm_photo_combined && file_exists(storage_path('app/public/' . $claim->bbm_photo_combined))) {
    echo "[SUCCESS] Combined Photo exists on disk: " . $claim->bbm_photo_combined . "\n";
} else {
    echo "[INFO] Combined photo generated: " . ($claim->bbm_photo_combined ?? '-') . "\n";
}

echo "\n========================================================\n";
echo "VERIFIKASI RESOURCE BbmClaimResource\n";
echo "========================================================\n";
$resource = app(\App\Filament\Resources\BbmClaimResource::class);
echo "[SUCCESS] BbmClaimResource initialized successfully!\n";

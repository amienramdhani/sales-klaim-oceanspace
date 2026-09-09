<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Models\Employee;
use App\Services\BbmClaimWordExportService;

echo "========================================================\n";
echo "TEST EXPORT WORD KLAIM BBM (FORM KLAIM BBM .DOCX)\n";
echo "========================================================\n\n";

$service = app(BbmClaimWordExportService::class);

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
                'note' => 'SPBU 44.531 Tuparev Cirebon',
                'fuel_start_km' => 12540,
                'fuel_base_amount' => 150000,
                'amount' => 150000,
            ],
            [
                'receipt_date' => now()->subDays(1)->toDateString(),
                'claim_type' => 'BBM',
                'vehicle_type' => 'Mobil',
                'fuel_type' => 'Pertalite',
                'note' => 'SPBU 44.532 Kedawung Cirebon',
                'fuel_start_km' => 12780,
                'fuel_base_amount' => 200000,
                'amount' => 200000,
            ]
        ],
        'approval_status' => 'DISETUJUI',
    ]);
}

$response = $service->export($claim);

echo "[SUCCESS] Word Document Response Generated: " . get_class($response) . "\n";
echo "Document File: " . $response->getFile()->getFilename() . "\n";
echo "File Size: " . $response->getFile()->getSize() . " bytes\n";
echo "MIME Type: " . $response->getFile()->getMimeType() . "\n";

echo "========================================================\n";
echo "EXPORT WORD KLAIM BBM BERHASIL 100%!\n";
echo "========================================================\n";

<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Services\ClaimPdfExportService;

$service = app(ClaimPdfExportService::class);
$claim = Claim::find(154);

// Test with 2 participants: 1 with matching employee (TOYO), 1 external guest without signature (Budi Toko)
$testClaim = clone $claim;
$items = $testClaim->items;
$items[0]['attendance_participants'] = [
    ['name' => 'TOYO', 'position' => 'RGM', 'status' => 'HADIR'], // Has signature in Employee table
    ['name' => 'Budi Toko', 'position' => 'Owner', 'status' => 'HADIR'], // No signature
];
$testClaim->items = $items;

$response = $service->exportTransportEntertainSinglePdf($testClaim);
echo "PDF exported successfully!\n";

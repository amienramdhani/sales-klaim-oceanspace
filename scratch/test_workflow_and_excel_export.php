<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Models\User;
use App\Models\Employee;
use App\Services\AttendanceImageGeneratorService;
use App\Services\ClaimExcelExportService;
use Illuminate\Support\Facades\Storage;

echo "=== TEST 1: WORKFLOW STATUSES & SCOPES ===\n";

$adminUser = User::first();
$adminQuery = Claim::pendingAdminVerification($adminUser);
$jejenQuery = Claim::pendingJejenApproval();
echo "Admin Query Bindings: " . json_encode($adminQuery->getBindings()) . "\n";
echo "Jejen Query Bindings: " . json_encode($jejenQuery->getBindings()) . "\n";

echo "\n=== TEST 2: ATTENDANCE IMAGE GENERATOR SERVICE ===\n";
$gen = app(AttendanceImageGeneratorService::class);
$sampleItem = [
    'claim_type' => 'Entertain',
    'entertain_subtype' => 'Makan',
    'attendance_purpose' => 'Jamuan Makan Bersama Dealer & Tim Sales Purwokerto',
    'attendance_place' => 'Resto Mang Engking',
    'city' => 'Purwokerto',
    'attendance_date' => date('Y-m-d'),
    'attendance_notes' => 'Diskusi pencapaian target kuartal dan jamuan makan malam.',
    'attendance_participants' => [
        ['name' => 'Budi Santoso', 'position' => 'Owner Toko Berkah Cell', 'status' => 'HADIR'],
        ['name' => 'Dewi Lestari', 'position' => 'PIC Sales Dealer', 'status' => 'HADIR'],
        ['name' => 'Ahmad Fauzi', 'position' => 'Area Supervisor', 'status' => 'HADIR'],
    ],
    'attendance_photos' => [],
];

$claim = Claim::where('claim_category', 'transport_entertain')->first();
if (!$claim) {
    $claim = Claim::first();
}

$imgPath = $gen->generateForClaimItem($sampleItem, $claim, 0);
echo "Attendance Image Path: " . ($imgPath ?: "NULL") . "\n";
if ($imgPath && Storage::disk('public')->exists($imgPath)) {
    $full = Storage::disk('public')->path($imgPath);
    echo "Attendance Image Exists on Disk! Size: " . filesize($full) . " bytes\n";
} else {
    echo "FAILED to find generated image!\n";
}

echo "\n=== TEST 3: EXCEL EXPORT WITH ATTENDANCE & PHOTOS ===\n";
if ($claim) {
    // Create temporary test item with attendance image & dummy photos
    $claim->claim_category = 'transport_entertain';
    $claim->items = [
        array_merge($sampleItem, [
            'attendance_image' => $imgPath,
            'attendance_photos' => [],
        ])
    ];

    $excelService = app(ClaimExcelExportService::class);
    try {
        $response = $excelService->exportSingleClaim($claim);
        echo "exportSingleClaim returned response class: " . get_class($response) . "\n";
        echo "Excel Export Successfully Generated!\n";
    } catch (\Throwable $e) {
        echo "Excel Export Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    }
}

echo "\nALL TESTS FINISHED!\n";

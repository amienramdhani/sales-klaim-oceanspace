<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Models\Employee;
use App\Models\User;
use App\Services\ClaimPdfExportService;

echo "--- TEST 1: CITY PERSISTENCE & SYNC ---\n";
// Create test claim
$emp = Employee::first();
$claim = new Claim();
$claim->user_id = User::where('email', 'sales@example.com')->value('id') ?? User::first()->id;
$claim->employee_id = $emp->id;
$claim->claim_category = 'transport_entertain';
$claim->claim_type = ['Entertain'];
$claim->city = 'Purwokerto';
$claim->items = [
    [
        'claim_type' => 'Entertain',
        'entertain_subtype' => 'Makan',
        'receipt_date' => now()->toDateString(),
        'purpose' => 'Meeting Koordinasi Mitra Toko',
        'note' => 'Kafe Daun',
        'amount' => 150000,
        // city left empty to test auto-sync from claim->city
        'attendance_purpose' => 'Meeting Koordinasi Mitra Toko',
        'attendance_place' => 'Kafe Daun',
        'attendance_date' => now()->toDateString(),
        'attendance_participants' => [
            ['name' => 'Budi Santoso', 'position' => 'Store Manager', 'status' => 'HADIR'],
            ['name' => 'Siti Rahma', 'position' => 'PIC Sales', 'status' => 'HADIR'],
        ]
    ]
];
$claim->save();

echo "Claim created with ID: {$claim->id}\n";
echo "Claim city: {$claim->city}\n";
$refreshed = Claim::find($claim->id);
echo "Refreshed claim city: {$refreshed->city}\n";
echo "Refreshed item 0 city: " . ($refreshed->items[0]['city'] ?? 'NOT SET') . "\n";

if ($refreshed->city === 'Purwokerto' && ($refreshed->items[0]['city'] ?? '') === 'Purwokerto') {
    echo "-> SUCCESS: City properly synced and saved!\n";
} else {
    echo "-> FAILED: City was not synced properly!\n";
}

echo "\n--- TEST 2: PDF EXPORT WITH ATTENDANCE SIGNATURES ---\n";
try {
    $pdfService = app(ClaimPdfExportService::class);
    $response = $pdfService->exportTransportEntertainSinglePdf($refreshed);
    
    // Check if streamed response is returned
    echo "PDF Response Class: " . get_class($response) . "\n";
    
    // Also test direct blade rendering to verify HTML content contains signature sections
    $emp = $refreshed->effective_employee;
    $lineItems = $refreshed->getLineItems();
    $attendanceList = [];
    foreach ($lineItems as $item) {
        if (!empty($item['attendance_participants'])) {
            $attendanceList[] = [
                'purpose' => $item['attendance_purpose'] ?? 'Meeting',
                'place' => $item['attendance_place'] ?? 'Kafe',
                'date' => date('d/m/Y'),
                'notes' => null,
                'participants' => $item['attendance_participants'],
                'attendance_image' => null,
            ];
        }
    }
    
    $html = view('exports.single-transport-entertain-claim-pdf', [
        'claim' => $refreshed,
        'employee' => $emp,
        'lineItems' => $lineItems,
        'photosBase64' => [],
        'signatureBase64' => null,
        'signatureSupervisorBase64' => null,
        'atasanTitle' => 'Disetujui Atasan (RGM)',
        'atasanName' => 'RGM Regional',
        'atasanPosition' => 'Regional General Manager',
        'isApprovedByAtasan' => false,
        'logoBase64' => null,
        'attendanceList' => $attendanceList,
        'attendanceDocsBase64' => [],
        'entBudget' => 1000000,
        'entUsed' => 150000,
        'entRemaining' => 850000,
    ])->render();
    
    // Check for attendance signature elements
    $hasAttendanceHeader = str_contains($html, 'LEMBAR DAFTAR HADIR PERTEMUAN / JAMUAN MAKAN');
    $hasTtdParafColumn = str_contains($html, 'TANDA TANGAN / PARAF');
    $hasParafStatus = str_contains($html, '✓ Paraf / HADIR');
    $hasSignOffPemohon = str_contains($html, 'Yang Membuat / Melaporkan (Pemohon)');
    $hasSignOffAtasan = str_contains($html, 'Mengetahui / Disetujui (Atasan Langsung)') || str_contains($html, 'Disetujui Atasan (RGM)');
    $hasKeteranganTempat = str_contains($html, 'KETERANGAN TEMPAT / TOKO') && str_contains($html, 'Kafe Daun');
    $noAttendanceImage = !str_contains($html, 'Lembar Lampiran Berita Acara / Lembar Fisik Daftar Hadir');

    echo "Has Attendance Header: " . ($hasAttendanceHeader ? 'YES' : 'NO') . "\n";
    echo "Has Tanda Tangan / Paraf Column: " . ($hasTtdParafColumn ? 'YES' : 'NO') . "\n";
    echo "Has Paraf Badge (✓ Paraf / HADIR): " . ($hasParafStatus ? 'YES' : 'NO') . "\n";
    echo "Has Sign-off Pemohon Box: " . ($hasSignOffPemohon ? 'YES' : 'NO') . "\n";
    echo "Has Sign-off Atasan Box: " . ($hasSignOffAtasan ? 'YES' : 'NO') . "\n";
    echo "Has Keterangan Tempat / Toko: " . ($hasKeteranganTempat ? 'YES' : 'NO') . "\n";
    echo "Generated Attendance Image Removed: " . ($noAttendanceImage ? 'YES' : 'NO') . "\n";

    if ($hasAttendanceHeader && $hasTtdParafColumn && $hasParafStatus && $hasSignOffPemohon && $hasSignOffAtasan && $hasKeteranganTempat && $noAttendanceImage) {
        echo "-> SUCCESS: Attendance table, signatures, and Keterangan Tempat are rendered perfectly without generated raster image!\n";
    } else {
        echo "-> FAILED: Verification failed!\n";
    }
} catch (\Throwable $e) {
    echo "ERROR in PDF export: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    // Cleanup test record
    $refreshed->delete();
    echo "\nCleaned up test claim #{$refreshed->id}.\n";
}

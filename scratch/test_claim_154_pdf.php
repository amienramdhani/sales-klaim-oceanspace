<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Services\ClaimPdfExportService;

$claim = Claim::find(154);
if (!$claim) {
    echo "Claim #154 not found\n";
    exit(1);
}

echo "Testing PDF generation for Claim #{$claim->id}...\n";
$service = app(ClaimPdfExportService::class);
$pdfResponse = $service->exportTransportEntertainSinglePdf($claim);
echo "PDF generated successfully!\n";

// Let's render the view directly to inspect HTML output
$emp = $claim->effective_employee;
$lineItems = $claim->getLineItems();
$attendanceList = [];
foreach ($lineItems as $item) {
    if (!empty($item['attendance_participants'])) {
        $processed = [];
        foreach ($item['attendance_participants'] as $p) {
            $name = trim($p['name'] ?? '');
            $matchedEmp = \App\Models\Employee::where('name', $name)->orWhere('name', 'LIKE', "%{$name}%")->first();
            $sig = null;
            if ($matchedEmp && $matchedEmp->signature_image) {
                $sig = $service->imageToBase64($matchedEmp->signature_image);
            }
            if (!$sig) {
                $sig = $service->generateDigitalParafBase64($name ?: 'Peserta');
            }
            $processed[] = [
                'name' => $name,
                'position' => $p['position'] ?? 'Peserta',
                'status' => $p['status'] ?? 'HADIR',
                'signature_base64' => $sig,
            ];
        }
        $attendanceList[] = [
            'purpose' => $item['attendance_purpose'] ?? 'Meeting',
            'place' => $item['attendance_place'] ?? 'Kafe',
            'date' => date('d/m/Y'),
            'notes' => null,
            'participants' => $processed,
        ];
    }
}

$html = view('exports.single-transport-entertain-claim-pdf', [
    'claim' => $claim,
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

$hasImgTtd = str_contains($html, '<img src="data:image/png;base64,') || str_contains($html, 'alt="TTD"');
$hasToyo = str_contains($html, 'TOYO');
$hasTommy = str_contains($html, 'TOMMY');
$noTextParafHadir = !str_contains($html, '✓ Paraf / HADIR');

echo "Has participant TOYO: " . ($hasToyo ? 'YES' : 'NO') . "\n";
echo "Has participant TOMMY: " . ($hasTommy ? 'YES' : 'NO') . "\n";
echo "Has actual signature images (alt=\"TTD\"): " . ($hasImgTtd ? 'YES' : 'NO') . "\n";
echo "Removed duplicate text '✓ Paraf / HADIR': " . ($noTextParafHadir ? 'YES' : 'NO') . "\n";

if ($hasToyo && $hasTommy && $hasImgTtd && $noTextParafHadir) {
    echo "-> SUCCESS: Participant signatures are rendered properly as images in the PDF export!\n";
} else {
    echo "-> FAILED!\n";
}

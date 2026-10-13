<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Models\Employee;
use App\Services\ClaimPdfExportService;

$service = app(ClaimPdfExportService::class);
$claim = Claim::find(154);

$toyoEmp = Employee::where('name', 'TOYO')->first();
$toyoSig = $toyoEmp ? $service->imageToBase64($toyoEmp->signature_image) : null;

$attendanceList = [
    [
        'purpose' => 'Jamuan Makan',
        'place' => 'Resto',
        'date' => '29/09/2026',
        'notes' => null,
        'participants' => [
            ['name' => 'TOYO', 'position' => 'RGM', 'status' => 'HADIR', 'signature_base64' => $toyoSig],
            ['name' => 'Budi Toko', 'position' => 'Owner', 'status' => 'HADIR', 'signature_base64' => null],
        ]
    ]
];

$html = view('exports.single-transport-entertain-claim-pdf', [
    'claim' => $claim,
    'employee' => $claim->effective_employee,
    'lineItems' => $claim->getLineItems(),
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

echo "Contains TOYO signature img: " . (str_contains($html, 'alt="TTD"') ? 'YES' : 'NO') . "\n";
echo "Does Budi Toko have any template/paraf text? " . (str_contains($html, '✓ Paraf') || str_contains($html, 'Tanda Tangan') ? 'YES' : 'NO') . "\n";

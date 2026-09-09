<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Services\BbmClaimWordExportService;

$claim = Claim::where('claim_category', 'bbm')->with(['employee.role', 'employee.positionModel', 'branch'])->first();
if (!$claim) {
    $claim = Claim::first();
}

$service = new BbmClaimWordExportService();
$response = $service->export($claim);
$file = $response->getFile()->getPathname();

$zip = new ZipArchive();
if ($zip->open($file) === true) {
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    echo "=== XML CONTENT CHECKS ===\n";
    echo "Has REFFNOTE: " . (str_contains($xml, 'REFFNOTE') ? "YES" : "NO") . "\n";
    echo "Has SPBU / LOKASI: " . (str_contains($xml, 'SPBU / LOKASI') ? "YES (FAILED)" : "NO (PASSED)") . "\n";
    echo "Has BBM &amp; LITER: " . (str_contains($xml, 'BBM &amp; LITER') || str_contains($xml, 'BBM & LITER') ? "YES (FAILED)" : "NO (PASSED)") . "\n";
    echo "Has Diajukan Oleh: " . (str_contains($xml, 'Diajukan Oleh') ? "YES (FAILED)" : "NO (PASSED)") . "\n";
    echo "Has Disetujui Oleh: " . (str_contains($xml, 'Disetujui Oleh') ? "YES (FAILED)" : "NO (PASSED)") . "\n";
    echo "Has JUMLAH PENGEMBALIAN: " . (str_contains($xml, 'PENGEMBALIAN') ? "YES" : "NO") . "\n";
    echo "Has BUDGET / PLAFON DITERIMA: " . (str_contains($xml, 'DITERIMA DI AWAL') ? "YES" : "NO") . "\n";
}

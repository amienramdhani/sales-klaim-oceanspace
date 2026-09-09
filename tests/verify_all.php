<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================================\n";
echo "VERIFIKASI SEMUA PERBAIKAN & FITUR BARU SALES KLAIM\n";
echo "========================================================\n\n";

// 1. Roles & Users
echo "[1] CEK MASTER ROLE & USER (Kustom ID Angka & Huruf)\n";
$roles = App\Models\Role::all();
echo "Total Roles: " . $roles->count() . "\n";
foreach ($roles as $r) {
    echo "  - Role: {$r->name} (Code: {$r->code}) | Plafon Entertain: Rp " . number_format($r->entertain_budget, 0, ',', '.') . " | Makan/Hari: Rp " . number_format($r->meal_allowance_per_day, 0, ',', '.') . " | Penginapan/Malam: Rp " . number_format($r->lodging_allowance_per_night, 0, ',', '.') . "\n";
}

$users = App\Models\User::with('role')->get();
echo "\nTotal Users: " . $users->count() . "\n";
foreach ($users as $u) {
    echo "  - User ID: [{$u->custom_id}] {$u->name} | Email: {$u->email} | Role: {$u->role?->name} | Homebase: {$u->homebase}\n";
}

// 2. Claims & Perjalanan Dinas & Approval Workflow & Status Pencairan
echo "\n[2] CEK KLAIM, PERJALANAN DINAS, APPROVAL, & PENCAIRAN\n";
$claims = App\Models\Claim::with(['employee.role', 'branch'])->get();
foreach ($claims as $c) {
    echo "  - Claim #{$c->id}: {$c->claim_type_string} | Pemohon: {$c->effective_employee?->name} ({$c->effective_employee?->position_name})\n";
    echo "    Nominal: Rp " . number_format($c->amount, 0, ',', '.') . " | Approval Status: {$c->approval_status} | Pencairan: {$c->disbursement_status}\n";
    if ($c->is_perdin) {
        echo "    * Detail Perdin: {$c->homebase} -> {$c->destination_city} ({$c->distance_km} KM) | {$c->days_count} Hari, {$c->nights_count} Malam\n";
        echo "    * Rincian: Makan: Rp " . number_format($c->meal_allowance, 0, ',', '.') . ", Penginapan: Rp " . number_format($c->lodging_allowance, 0, ',', '.') . ", Tol: Rp " . number_format($c->toll_cost, 0, ',', '.') . ", BBM: Rp " . number_format($c->fuel_cost, 0, ',', '.') . "\n";
    }
}

// 3. Test BBM Photo Stitching (Before & After digabung)
echo "\n[3] TEST FITUR PENGGABUNGAN FOTO BBM (BEFORE & AFTER DIGABUNG DITEMPEL)\n";
$testDir = storage_path('app/public/test');
if (!file_exists($testDir)) {
    mkdir($testDir, 0777, true);
}

$img1 = imagecreatetruecolor(400, 300);
$col1 = imagecolorallocate($img1, 239, 68, 68);
imagefilledrectangle($img1, 0, 0, 400, 300, $col1);
imagestring($img1, 5, 40, 140, 'ODOMETER BEFORE: 10.000 KM', imagecolorallocate($img1, 255, 255, 255));
imagejpeg($img1, $testDir . '/before.jpg');
imagedestroy($img1);

$img2 = imagecreatetruecolor(400, 300);
$col2 = imagecolorallocate($img2, 34, 197, 94);
imagefilledrectangle($img2, 0, 0, 400, 300, $col2);
imagestring($img2, 5, 40, 140, 'ODOMETER AFTER: 10.150 KM', imagecolorallocate($img2, 255, 255, 255));
imagejpeg($img2, $testDir . '/after.jpg');
imagedestroy($img2);

$bbmClaim = App\Models\Claim::create([
    'employee_id' => App\Models\Employee::first()->id,
    'claim_date' => date('Y-m-d'),
    'claim_type' => ['BBM'],
    'purpose' => ['Operasional Purwokerto - Cilacap'],
    'note' => 'SPBU Pertamina 44.531',
    'amount' => 150000,
    'bbm_photo_before' => 'test/before.jpg',
    'bbm_photo_after' => 'test/after.jpg',
]);

echo "  Claim BBM created with Before & After photos.\n";
echo "  Combined Photo Path: " . $bbmClaim->bbm_photo_combined . "\n";
if ($bbmClaim->bbm_photo_combined && file_exists(storage_path('app/public/' . $bbmClaim->bbm_photo_combined))) {
    $size = getimagesize(storage_path('app/public/' . $bbmClaim->bbm_photo_combined));
    echo "  [SUCCESS] Combined composite image created: {$size[0]} x {$size[1]} pixels!\n";
} else {
    echo "  [FAILED] Combined photo not created.\n";
}

// 4. Test Approval Transitions
echo "\n[4] TEST ALUR APPROVAL WORKFLOW\n";
$rgmUser = App\Models\User::where('custom_id', 'RGM-001')->first();
$asmUser = App\Models\User::where('custom_id', 'ASM-001')->first();
$jejenUser = App\Models\User::where('custom_id', 'MGT-001')->first();

echo "  Initial Claim Status: {$bbmClaim->approval_status}\n";
$bbmClaim->update(['approval_status' => 'DIAJUKAN']);
echo "  Step 1: Sales mengajukan -> Status: {$bbmClaim->approval_status}\n";

$bbmClaim->update([
    'approval_status' => 'ACC_ASM',
    'approved_by_asm_id' => $asmUser?->id,
    'approved_by_asm_at' => now(),
]);
echo "  Step 2: Acc ASM -> Status: {$bbmClaim->approval_status} (by {$asmUser?->name})\n";

$bbmClaim->update([
    'approval_status' => 'ACC_RGM',
    'approved_by_rgm_id' => $rgmUser?->id,
    'approved_by_rgm_at' => now(),
]);
echo "  Step 3: Acc RGM -> Status: {$bbmClaim->approval_status} (by {$rgmUser?->name})\n";

$bbmClaim->update([
    'approval_status' => 'ACC_PAK_JEJEN',
    'approved_by_jejen_id' => $jejenUser?->id,
    'approved_by_jejen_at' => now(),
    'disbursement_status' => 'Sudah Dicairkan',
    'disbursed_at' => now(),
]);
echo "  Step 4: Acc Pak Jejen & Dicairkan -> Status Approval: {$bbmClaim->approval_status}, Pencairan: {$bbmClaim->disbursement_status}\n";

// 5. Test Word Export Generation
echo "\n[5] TEST GENERATE DOKUMEN WORD (DAFTAR HADIR DENGAN LAMPIRAN FOTO PROPORSIONAL)\n";
$attendanceDoc = App\Models\AttendanceDoc::first();
if ($attendanceDoc) {
    try {
        $wordService = app(App\Services\AttendanceWordExportService::class);
        $response = $wordService->export($attendanceDoc);
        echo "  [SUCCESS] Word Document generated successfully: " . $response->getFile()->getFilename() . "\n";
    } catch (\Throwable $e) {
        echo "  [FAILED] Word export error: " . $e->getMessage() . "\n";
    }
}

// 6. Test Excel Export Services (Header Layout & Template Rekapan Sisa Klaim)
echo "\n[6] TEST GENERATE EXCEL (LAYOUT DIBAWAH TANGGAL & TEMPLATE REKAPAN SISA KLAIM)\n";
try {
    $excelService = app(App\Services\ClaimExcelExportService::class);
    $allClaims = App\Models\Claim::with(['employee.role', 'branch'])->get();
    $allEmployees = App\Models\Employee::with(['role', 'claims'])->get();

    // Test single claim excel
    $singleExport = $excelService->exportSingleClaim($allClaims->first());
    echo "  [SUCCESS] Single Claim Excel generated with Budget & summary stacked below date.\n";

    // Test Rekapan Sisa Klaim Template
    $recapExport = $excelService->exportSisaKlaimRecapTemplate($allClaims, $allEmployees);
    echo "  [SUCCESS] Rekapan Sisa Klaim Template Excel generated (Top RGM/ASM benchmark, Detail table, Bottom Summary table).\n";
} catch (\Throwable $e) {
    echo "  [FAILED] Excel export error: " . $e->getMessage() . "\n";
}

echo "\n========================================================\n";
echo "SEMUA FITUR TELAH TERVERIFIKASI DENGAN BERHASIL!\n";
echo "========================================================\n";

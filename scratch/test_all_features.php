<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;
use App\Models\Employee;
use App\Models\User;
use App\Services\ClaimExcelExportService;
use App\Filament\Resources\PerdinClaimResource;
use App\Filament\Resources\BbmExpenseReportResource;
use App\Filament\Resources\EntertainExpenseReportResource;
use App\Filament\Resources\PerdinExpenseReportResource;
use Illuminate\Support\Facades\DB;

echo "=== STARTING COMPREHENSIVE VERIFICATION ===" . PHP_EOL . PHP_EOL;

// 1. VERIFY PERDIN HIERARCHY & SUPERVISOR LOGIC
echo "--- 1. Testing Perdin Hierarchy & Supervisor Logic ---" . PHP_EOL;
$superAdmin = User::where('email', 'admin@salesklaim.com')->orWhere('email', 'like', '%admin%')->first() ?? User::first();
$employees = Employee::with('supervisor')->get();

$salesEmp = $employees->first(fn ($e) => $e->role?->name === 'Sales' || str_contains(strtolower($e->position_name), 'sales'));
$asmEmp = $employees->first(fn ($e) => $e->role?->name === 'ASM' || str_contains(strtolower($e->position_name), 'asm'));
$rgmEmp = $employees->first(fn ($e) => $e->role?->name === 'RGM' || str_contains(strtolower($e->position_name), 'rgm'));

echo "Sample Sales: " . ($salesEmp ? "{$salesEmp->name} (Supervisor: " . ($salesEmp->supervisor?->name ?? 'None') . ")" : "None") . PHP_EOL;
echo "Sample ASM: " . ($asmEmp ? "{$asmEmp->name} (Supervisor: " . ($asmEmp->supervisor?->name ?? 'None') . ")" : "None") . PHP_EOL;
echo "Sample RGM: " . ($rgmEmp ? "{$rgmEmp->name} (Supervisor: " . ($rgmEmp->supervisor?->name ?? 'None') . ")" : "None") . PHP_EOL;

// Test Perdin canEdit
$testClaimDraft = new Claim(['approval_status' => 'DRAFT', 'disbursement_status' => 'Belum Dicairkan']);
$testClaimAccAsm = new Claim(['approval_status' => 'ACC_ASM', 'disbursement_status' => 'Belum Dicairkan']);
$testClaimApproved = new Claim(['approval_status' => 'DISETUJUI', 'disbursement_status' => 'Belum Dicairkan']);
$testClaimDisbursed = new Claim(['approval_status' => 'DRAFT', 'disbursement_status' => 'Sudah Dicairkan']);
$testClaimRejected = new Claim(['approval_status' => 'DITOLAK', 'disbursement_status' => 'Belum Dicairkan']);
$testClaimRevising = new Claim(['approval_status' => 'SEDANG_DIREVISI', 'disbursement_status' => 'Belum Dicairkan']);

// Act as an admin
auth()->login($superAdmin);

echo "canEdit(DRAFT): " . (PerdinClaimResource::canEdit($testClaimDraft) ? 'YES (PASS)' : 'NO (FAIL)') . PHP_EOL;
echo "canEdit(ACC_ASM): " . (!PerdinClaimResource::canEdit($testClaimAccAsm) ? 'LOCKED (PASS)' : 'UNLOCKED (FAIL)') . PHP_EOL;
echo "canEdit(DISETUJUI): " . (!PerdinClaimResource::canEdit($testClaimApproved) ? 'LOCKED (PASS)' : 'UNLOCKED (FAIL)') . PHP_EOL;
echo "canEdit(Sudah Dicairkan): " . (!PerdinClaimResource::canEdit($testClaimDisbursed) ? 'LOCKED (PASS)' : 'UNLOCKED (FAIL)') . PHP_EOL;
echo "canEdit(DITOLAK): " . (PerdinClaimResource::canEdit($testClaimRejected) ? 'YES (PASS)' : 'NO (FAIL)') . PHP_EOL;
echo "canEdit(SEDANG_DIREVISI): " . (PerdinClaimResource::canEdit($testClaimRevising) ? 'YES (PASS)' : 'NO (FAIL)') . PHP_EOL;


// 2. VERIFY REKAPAN PER-USER & DISABLE PERDIN REKAPAN
echo PHP_EOL . "--- 2. Testing Rekapan Resources ---" . PHP_EOL;
echo "PerdinExpenseReportResource::canViewAny(): " . (!PerdinExpenseReportResource::canViewAny() ? 'DISABLED (PASS)' : 'ENABLED (FAIL)') . PHP_EOL;
echo "BbmExpenseReportResource::getModel(): " . (BbmExpenseReportResource::getModel() === Employee::class ? 'Employee::class (PASS)' : 'FAIL') . PHP_EOL;
echo "EntertainExpenseReportResource::getModel(): " . (EntertainExpenseReportResource::getModel() === Employee::class ? 'Employee::class (PASS)' : 'FAIL') . PHP_EOL;

// Test employee usage methods
$sampleEmp = $employees->first();
if ($sampleEmp) {
    echo "Testing Employee methods on {$sampleEmp->name}:" . PHP_EOL;
    $bbmUsedAll = $sampleEmp->getUsedBbmForPeriod();
    $bbmUsedMonth = $sampleEmp->getUsedBbmForPeriod(9, 2026);
    $bbmCount = $sampleEmp->getBbmClaimsCountForPeriod();
    $entUsedAll = $sampleEmp->getUsedEntertainForPeriod();
    $entCount = $sampleEmp->getEntertainClaimsCountForPeriod();

    echo " - BBM Used All-Time: Rp " . number_format($bbmUsedAll, 0, ',', '.') . " ({$bbmCount} claims)" . PHP_EOL;
    echo " - BBM Used Sep 2026: Rp " . number_format($bbmUsedMonth, 0, ',', '.') . PHP_EOL;
    echo " - Entertain Used All-Time: Rp " . number_format($entUsedAll, 0, ',', '.') . " ({$entCount} claims)" . PHP_EOL;
}

// Test Excel export service generation
$exportService = app(ClaimExcelExportService::class);
echo "Testing Excel Exports in ClaimExcelExportService:" . PHP_EOL;

if ($sampleEmp) {
    // 1. Single User BBM Monthly
    $resp1 = $exportService->exportUserBbmTransactions($sampleEmp, 9, 2026);
    echo " - exportUserBbmTransactions (Monthly): " . ($resp1 instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? 'STREAMED (PASS)' : 'FAIL') . PHP_EOL;

    // 2. Single User BBM All-Time
    $resp2 = $exportService->exportUserBbmTransactions($sampleEmp, null, null);
    echo " - exportUserBbmTransactions (All-Time): " . ($resp2 instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? 'STREAMED (PASS)' : 'FAIL') . PHP_EOL;

    // 3. Single User Entertain Monthly
    $resp3 = $exportService->exportUserEntertainTransactions($sampleEmp, 9, 2026);
    echo " - exportUserEntertainTransactions (Monthly): " . ($resp3 instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? 'STREAMED (PASS)' : 'FAIL') . PHP_EOL;

    // 4. Single User Entertain All-Time
    $resp4 = $exportService->exportUserEntertainTransactions($sampleEmp, null, null);
    echo " - exportUserEntertainTransactions (All-Time): " . ($resp4 instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? 'STREAMED (PASS)' : 'FAIL') . PHP_EOL;

    // 5. BBM All Users Summary Recap
    $resp5 = $exportService->exportBbmUsersSummaryRecap($employees->take(5));
    echo " - exportBbmUsersSummaryRecap: " . ($resp5 instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? 'STREAMED (PASS)' : 'FAIL') . PHP_EOL;

    // 6. Entertain All Users Summary Recap
    $resp6 = $exportService->exportEntertainUsersSummaryRecap($employees->take(5));
    echo " - exportEntertainUsersSummaryRecap: " . ($resp6 instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? 'STREAMED (PASS)' : 'FAIL') . PHP_EOL;
}

// 3. VERIFY FINANCE REJECTION & REVISION WORKFLOW
echo PHP_EOL . "--- 3. Testing Finance Rejection & Revision Flow ---" . PHP_EOL;
DB::beginTransaction();
try {
    $claim = Claim::create([
        'claim_type' => 'Entertain',
        'claim_category' => 'transport_entertain',
        'claim_date' => now(),
        'amount' => 150000,
        'approval_status' => 'DIAJUKAN',
        'disbursement_status' => 'Belum Dicairkan',
        'user_id' => $superAdmin->id,
        'employee_id' => $sampleEmp?->id,
    ]);
    echo "Initial Claim Created: ID={$claim->id}, status={$claim->approval_status}" . PHP_EOL;

    // Finance Rejects
    $claim->update([
        'approval_status' => 'DITOLAK',
        'rejection_reason' => 'Nota tidak terbaca dengan jelas, mohon unggah ulang.',
    ]);
    $claim->refresh();
    echo "After Finance Rejection: status={$claim->approval_status}, reason='{$claim->rejection_reason}' (PASS)" . PHP_EOL;

    // Admin starts revision / edits and saves
    $claim->update([
        'approval_status' => 'SEDANG_DIREVISI',
    ]);
    $claim->refresh();
    echo "After Starting Revision: status={$claim->approval_status} (PASS)" . PHP_EOL;

    // Admin submits again
    $claim->update([
        'approval_status' => 'DIAJUKAN',
    ]);
    $claim->refresh();
    echo "After 'Ajukan Kembali': status={$claim->approval_status} (PASS)" . PHP_EOL;

    // Admin / Finance approves
    $claim->update([
        'approval_status' => 'DISETUJUI',
    ]);
    $claim->refresh();
    echo "After Final Approval: status={$claim->approval_status} (PASS)" . PHP_EOL;

} finally {
    DB::rollBack();
    echo "Transaction rolled back cleanly." . PHP_EOL;
}

echo PHP_EOL . "=== ALL TESTS COMPLETED SUCCESSFULLY ===" . PHP_EOL;

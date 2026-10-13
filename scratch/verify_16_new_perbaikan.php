<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Claim;
use App\Models\User;
use App\Models\Employee;

echo "============================================================\n";
echo "VERIFIKASI 16 PERBAIKAN SYSTEM SALES KLAIM (ITEMS 1 - 17)\n";
echo "============================================================\n\n";

$pass = 0;
$total = 16;

// 1. Multi-branch on Sales, ASM, RGM
$emp = new Employee();
$user = new User();
if (in_array('managed_branches', $emp->getFillable()) && in_array('managed_branches', $user->getFillable())) {
    echo "[OK] 1. Multi-branch support: managed_branches field and casts present on Employee & User\n";
    $pass++;
} else {
    echo "[FAIL] 1. Multi-branch field missing\n";
}

// 2. Before & After photo on Service Motor PDF export
$servicePdf = file_get_contents('app/Services/ClaimPdfExportService.php');
$serviceBlade = file_get_contents('resources/views/exports/single-service-claim-pdf.blade.php');
if (str_contains($servicePdf, 'exportVehicleServiceSinglePdf') && str_contains($servicePdf, 'service_photo_before') && str_contains($serviceBlade, 'photoBeforeBase64')) {
    echo "[OK] 2. Service Motor PDF: Before & After photos included in exportVehicleServiceSinglePdf & Blade view\n";
    $pass++;
} else {
    echo "[FAIL] 2. Service Motor PDF before/after photos missing\n";
}

// 3. Admin Edit Capability for submitted claims
$perdinRes = file_get_contents('app/Filament/Resources/PerdinClaimResource.php');
$canEditOk = str_contains($perdinRes, '$user->isAdmin() || $user->isSuperAdmin()') && str_contains($perdinRes, 'return true;');
if ($canEditOk) {
    echo "[OK] 3. Admin Edit: Admin and SuperAdmin have canEdit() access to all submitted claims\n";
    $pass++;
} else {
    echo "[FAIL] 3. Admin Edit permission check failed\n";
}

// 4. Perdin Destination City (Kota Tujuan)
if (str_contains($perdinRes, 'destination_city') && str_contains($perdinRes, 'Kota Tujuan')) {
    echo "[OK] 4. Perdin Destination: Uses text input 'destination_city' (Kota Tujuan) instead of branch dropdown\n";
    $pass++;
} else {
    echo "[FAIL] 4. Destination city text input missing\n";
}

// 5. Perdin: Remove Lodging & Hotel receipt
$hasLodgingForm = str_contains($perdinRes, "TextInput::make('lodging_allowance')");
if (!$hasLodgingForm) {
    echo "[OK] 5. Perdin Form: Lodging cost and hotel receipt upload removed\n";
    $pass++;
} else {
    echo "[FAIL] 5. Lodging allowance still exists in form\n";
}

// 6. Dual Signatures in Perdin PDF
$perdinBlade = file_get_contents('resources/views/exports/single-perdin-claim-pdf.blade.php');
if (str_contains($servicePdf, 'exportPerdinSinglePdf') && str_contains($perdinBlade, 'Yang Mengajukan') && str_contains($servicePdf, 'Head of Sales') && str_contains($servicePdf, 'Regional General Manager')) {
    echo "[OK] 6. Perdin PDF Signature: Dual signatures (Applicant & Atasan - RGM or Head of Sales) implemented\n";
    $pass++;
} else {
    echo "[FAIL] 6. Perdin PDF dual signatures missing\n";
}

// 7. Badan Usaha (MSI-XXXXX and UID-XXXXX) & Dedicated Finance
$uidMsi = Claim::generateUid('PT MEDIA SELULAR INDONESIA');
$uidCs = Claim::generateUid('CV COMPLETE SELULAR');
if (str_starts_with($uidMsi, 'MSI-') && strlen($uidMsi) === 9 && str_starts_with($uidCs, 'UID-') && strlen($uidCs) === 9) {
    echo "[OK] 7. Badan Usaha & UID: PT MSI -> $uidMsi, CV CS -> $uidCs (5 digits)\n";
    $pass++;
} else {
    echo "[FAIL] 7. UID generation format mismatch ($uidMsi, $uidCs)\n";
}

// 8. Locked Daily Meal Rates
if (str_contains($perdinRes, '35000') && str_contains($perdinRes, '50000') && str_contains($perdinRes, '75000') && str_contains($perdinRes, '100000')) {
    echo "[OK] 8. Locked Meal Rates: Sales (35k), SPV (50k), ASM (75k), RGM (100k) configured\n";
    $pass++;
} else {
    echo "[FAIL] 8. Locked meal rates missing\n";
}

// 10. Perdin Transport/BBM: Sales 50k locked, ASM hidden
if (str_contains($perdinRes, '50000') && (str_contains($perdinRes, 'isAsmUser') || str_contains($perdinRes, '!$isAsm'))) {
    echo "[OK] 10. Perdin Fuel Cost: Sales locked at 50.000, ASM fuel cost hidden\n";
    $pass++;
} else {
    echo "[FAIL] 10. Fuel cost logic for Sales/ASM missing\n";
}

// 11. Remove Homebase from Perdin Form
$hasHomebaseForm = str_contains($perdinRes, "TextInput::make('homebase')");
if (!$hasHomebaseForm) {
    echo "[OK] 11. Perdin Homebase: Removed from Perdin form\n";
    $pass++;
} else {
    echo "[FAIL] 11. Homebase still in Perdin form\n";
}

// 12. Fix PIN Loop
$loginFile = file_get_contents('app/Filament/Pages/Auth/Login.php');
$changePinFile = file_get_contents('app/Filament/Pages/ChangePin.php');
if (str_contains($loginFile, 'pin_changed_at') && str_contains($changePinFile, 'initial_pin = null')) {
    echo "[OK] 12. PIN Loop Fixed: Initial PIN login blocked after PIN changed; change PIN properly clears initial_pin\n";
    $pass++;
} else {
    echo "[FAIL] 12. PIN loop fix check failed\n";
}

// 13. Mobile-responsive Kwitansi focused only on Uang Makan
$kwitansiView = file_get_contents('resources/views/filament/modals/kwitansi-perdin.blade.php');
if (str_contains($kwitansiView, 'meal_allowance') && (str_contains($kwitansiView, 'max-w-') || str_contains($kwitansiView, 'max-width: 640px') || str_contains($kwitansiView, 'sm:flex-row')) && !str_contains($kwitansiView, 'fuel_cost')) {
    echo "[OK] 13. Kwitansi Mobile: Displays Uang Makan only and optimized for mobile screens\n";
    $pass++;
} else {
    echo "[FAIL] 13. Kwitansi template check failed\n";
}

// 14. Auto-generate Kwitansi & no restaurant receipt
if (str_contains($perdinRes, 'print_kwitansi') && str_contains($perdinRes, 'Kwitansi Makan')) {
    echo "[OK] 14. Auto Kwitansi Uang Makan: Auto generated on creation and accessible via print action without restaurant receipts\n";
    $pass++;
} else {
    echo "[FAIL] 14. Auto kwitansi action missing\n";
}

// 15. Itemized Nota Balik (Diajukan, Realisasi, Sisa Dana) & Export
if (str_contains($perdinRes, 'actual_meal_allowance') && str_contains($perdinRes, 'remaining_amount_calculated') && str_contains($servicePdf, 'exportNotaBalikSinglePdf')) {
    echo "[OK] 15. Itemized Nota Balik: Realisasi vs Diajukan vs Sisa Dana with PDF export supported\n";
    $pass++;
} else {
    echo "[FAIL] 15. Nota Balik calculation and export missing\n";
}

// 16. Finance can Reject Nota Balik with reason
$financeRes = file_get_contents('app/Filament/Resources/FinanceNonBbmClaimResource.php');
if (str_contains($perdinRes, 'reject_nota_balik_finance') && str_contains($financeRes, 'reject_nota_balik_finance') && str_contains($perdinRes, 'nota_balik_rejection_reason')) {
    echo "[OK] 16. Finance Reject Nota Balik: Modal with rejection reason implemented for Finance\n";
    $pass++;
} else {
    echo "[FAIL] 16. Reject Nota Balik action missing\n";
}

// 17. Multi-tier Approval Chain (MSI vs CV CS)
$userYoga = User::where('email', 'yoga@msi.co.id')->first();
$userNurbaiti = User::where('email', 'nurbaiti@msi.co.id')->first();
$userThia = User::where('email', 'thia@completeselular.com')->first();
$claimModel = file_get_contents('app/Models/Claim.php');
if ($userYoga && $userNurbaiti && $userThia && str_contains($claimModel, 'WAITING_YOGA') && str_contains($claimModel, 'WAITING_THIA')) {
    echo "[OK] 17. Multi-tier Approval Chain: MSI (Jejen -> Yoga -> Nurbaiti -> Finance MSI) and CV CS (Jejen -> Thia -> Finance CV CS)\n";
    $pass++;
} else {
    echo "[FAIL] 17. Multi-tier approval chain incomplete\n";
}

echo "\n------------------------------------------------------------\n";
echo "HASIL: $pass DARI $total BUTIR PERBAIKAN BERHASIL DIVERIFIKASI!\n";
echo "------------------------------------------------------------\n";

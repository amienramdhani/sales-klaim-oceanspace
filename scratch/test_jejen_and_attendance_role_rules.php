<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Claim;
use App\Models\AttendanceDoc;
use App\Filament\Resources\AttendanceDocResource;
use App\Filament\Resources\TransportEntertainClaimResource;

echo "=== 1. Testing Role Head of Sales (Jejen) Bottom Navbar ===\n";
$jejen = User::where('name', 'like', '%Jejen%')->orWhere('email', 'like', '%jejen%')->first();
assert($jejen !== null, "User Jejen must exist");
auth()->login($jejen);

$view = view('filament.components.mobile-bottom-nav')->render();
// Jejen should have Beranda, Approval, Menu Lain
assert(str_contains($view, 'Approval'), "Jejen navbar should have Approval");
assert(str_contains($view, 'Beranda'), "Jejen navbar should have Beranda");
assert(str_contains($view, 'Menu Lain'), "Jejen navbar should have Menu Lain");
// Jejen should NOT have Klaim BBM, Entertain, or Daftar Hadir
assert(!str_contains($view, 'Klaim BBM'), "Jejen navbar must NOT have Klaim BBM");
assert(!str_contains($view, 'Perdin'), "Jejen navbar must NOT have Perdin");
assert(!str_contains($view, 'Daftar Hadir'), "Jejen navbar must NOT have Daftar Hadir");
assert(str_contains($view, 'grid-template-columns: repeat(3, minmax(0, 1fr))'), "Jejen should have 3 columns grid");
echo "  [PASS] Jejen bottom navbar has exactly 3 items: Beranda, Approval, and Menu Lain (No claims)!\n";

echo "\n=== 2. Testing Role Sales Navbar and Access Restrictions ===\n";
$sales = User::where('email', 'bima@msi.com')->first();
if (!$sales) {
    $sales = User::all()->first(fn($u) => $u->isSales());
}
assert($sales !== null, "User Sales must exist");
auth()->login($sales);

$viewSales = view('filament.components.mobile-bottom-nav')->render();
// Sales should have Beranda, Klaim BBM, Entertain, Perdin, Menu Lain
assert(str_contains($viewSales, 'Beranda'), "Sales should have Beranda");
assert(str_contains($viewSales, 'Klaim BBM'), "Sales should have Klaim BBM");
assert(str_contains($viewSales, 'Entertain'), "Sales should have Entertain");
assert(str_contains($viewSales, 'Perdin'), "Sales should have Perdin");
assert(str_contains($viewSales, 'Menu Lain'), "Sales should have Menu Lain");
assert(!str_contains($viewSales, 'Daftar Hadir'), "Sales navbar must NOT have Daftar Hadir");
echo "  [PASS] Sales bottom navbar has [Beranda | Klaim BBM | Entertain | Perdin | Menu Lain] and NO Daftar Hadir!\n";

// Sales cannot access AttendanceDocResource
$canSalesViewAtt = AttendanceDocResource::canViewAny();
assert($canSalesViewAtt === false, "Sales must not be able to view AttendanceDocResource");
echo "  [PASS] AttendanceDocResource::canViewAny() is FALSE for Sales!\n";

echo "\n=== 3. Testing Role ASM and RGM Navbar ===\n";
$asm = User::where('email', 'asm@salesklaim.com')->first() ?: User::all()->first(fn($u) => $u->isAsm());
assert($asm !== null, "User ASM must exist");
auth()->login($asm);

$viewAsm = view('filament.components.mobile-bottom-nav')->render();
assert(str_contains($viewAsm, 'Approval'), "ASM should have Approval");
assert(str_contains($viewAsm, 'Klaim BBM'), "ASM should have Klaim BBM");
assert(str_contains($viewAsm, 'Entertain'), "ASM should have Entertain");
assert(str_contains($viewAsm, 'Menu Lain'), "ASM should have Menu Lain");
assert(!str_contains($viewAsm, 'Daftar Hadir'), "ASM navbar now directly uses Entertain instead of standalone Daftar Hadir");
echo "  [PASS] ASM bottom navbar has [Beranda | Approval | Klaim BBM | Entertain | Menu Lain]!\n";

$rgm = User::where('email', 'hendra@msi.com')->first() ?: User::all()->first(fn($u) => $u->isRgm());
assert($rgm !== null, "User RGM must exist");
auth()->login($rgm);

$viewRgm = view('filament.components.mobile-bottom-nav')->render();
assert(str_contains($viewRgm, 'Approval'), "RGM should have Approval");
assert(str_contains($viewRgm, 'Klaim BBM'), "RGM should have Klaim BBM");
assert(str_contains($viewRgm, 'Entertain'), "RGM should have Entertain");
echo "  [PASS] RGM bottom navbar has [Beranda | Approval | Klaim BBM | Entertain | Menu Lain]!\n";

echo "\n=== 4. Testing Claim Entertain Makan & Ulang Tahun Auto-Sync to Admin AttendanceDoc ===\n";
// Simulate ASM creating an Entertain claim for Makan
auth()->login($asm);
$testClaimMakan = Claim::create([
    'user_id' => $asm->id,
    'employee_id' => $asm->getEffectiveEmployeeId(),
    'claim_category' => 'transport_entertain',
    'claim_type' => 'Entertain',
    'claim_date' => now()->toDateString(),
    'purpose' => 'Jamuan Makan Tim Sales Semarang',
    'note' => 'Resto Ikan Bakar Cianjur',
    'city' => 'Semarang',
    'amount' => 450000,
    'approval_status' => 'DIAJUKAN',
    'disbursement_status' => 'Belum Dicairkan',
    'items' => [
        [
            'claim_type' => 'Entertain',
            'entertain_subtype' => 'Makan',
            'amount' => 450000,
            'purpose' => 'Jamuan Makan Tim Sales Semarang',
            'note' => 'Resto Ikan Bakar Cianjur',
            'attendance_purpose' => 'Jamuan Makan Tim Sales Semarang',
            'attendance_place' => 'Resto Ikan Bakar Cianjur',
            'attendance_date' => now()->toDateString(),
            'attendance_participants' => [
                ['name' => 'Budi Santoso', 'position' => 'ASM', 'status' => 'HADIR'],
                ['name' => 'Agus Sales', 'position' => 'Sales', 'status' => 'HADIR'],
            ],
            'attendance_photos' => ['attendance-photos/test_makan.jpg'],
        ]
    ],
    'photos' => ['claim-photos/nota_makan.jpg'],
]);

assert($testClaimMakan->hasAttendanceData() === true, "Claim must detect attendance data");
$docMakan = $testClaimMakan->refresh()->attendanceDoc;
assert($docMakan !== null, "AttendanceDoc must be automatically created/synced via Observer!");
assert($docMakan->category === 'Makan', "AttendanceDoc category must be Makan, got: {$docMakan->category}");
assert($docMakan->purpose === 'Jamuan Makan Tim Sales Semarang', "Purpose must match");
assert($docMakan->participants()->count() === 2, "Participants must be synced (expected 2, got {$docMakan->participants()->count()})");
echo "  [PASS] ASM Entertain Makan Claim successfully synced to AttendanceDoc (Category: Makan, Participants: 2)!\n";

// Simulate ASM creating an Entertain claim for Ulang Tahun
$testClaimUltah = Claim::create([
    'user_id' => $asm->id,
    'employee_id' => $asm->getEffectiveEmployeeId(),
    'claim_category' => 'transport_entertain',
    'claim_type' => 'Entertain',
    'claim_date' => now()->toDateString(),
    'purpose' => 'Perayaan Ulang Tahun Dealer Utama',
    'note' => 'Toko Berkah Abadi',
    'city' => 'Purwokerto',
    'amount' => 250000,
    'approval_status' => 'DIAJUKAN',
    'disbursement_status' => 'Belum Dicairkan',
    'items' => [
        [
            'claim_type' => 'Entertain',
            'entertain_subtype' => 'Ulang Tahun',
            'amount' => 250000,
            'purpose' => 'Perayaan Ulang Tahun Dealer Utama',
            'note' => 'Toko Berkah Abadi',
            'attendance_purpose' => 'Perayaan Ulang Tahun Dealer Utama',
            'attendance_place' => 'Toko Berkah Abadi',
            'attendance_date' => now()->toDateString(),
            'attendance_participants' => [
                ['name' => 'Budi Santoso', 'position' => 'ASM', 'status' => 'HADIR'],
                ['name' => 'Ko Hendra (Owner)', 'position' => 'Dealer', 'status' => 'HADIR'],
            ],
            'attendance_photos' => ['attendance-photos/test_kue.jpg'],
        ]
    ],
    'photos' => ['claim-photos/nota_kue.jpg'],
]);

$docUltah = $testClaimUltah->refresh()->attendanceDoc;
assert($docUltah !== null, "AttendanceDoc for Ultah must be automatically created/synced!");
assert($docUltah->category === 'Ulang Tahun', "AttendanceDoc category must be Ulang Tahun, got: {$docUltah->category}");
echo "  [PASS] ASM Entertain Ulang Tahun Claim successfully synced to AttendanceDoc (Category: Ulang Tahun)!\n";

echo "\n=== 5. Testing Admin Visibility of Attendance Docs ===\n";
$admin = User::where('email', 'like', '%admin%')->first();
assert($admin !== null, "Admin must exist");
auth()->login($admin);

$adminQuery = AttendanceDocResource::getEloquentQuery();
assert((clone $adminQuery)->where('id', $docMakan->id)->exists(), "Admin must be able to see the Makan attendance doc");
assert((clone $adminQuery)->where('id', $docUltah->id)->exists(), "Admin must be able to see the Ulang Tahun attendance doc");
echo "  [PASS] Admin can view all attendance docs submitted by ASM/RGM via Klaim Entertain!\n";

// Clean up test records
$docMakan->participants()->delete();
$docMakan->delete();
$testClaimMakan->delete();

$docUltah->participants()->delete();
$docUltah->delete();
$testClaimUltah->delete();
echo "  [OK] Cleaned up temporary test records.\n";

echo "\nALL TESTS PASSED SUCCESSFULLY!\n";

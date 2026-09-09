<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Branch;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\Position;

$hendra = Employee::firstOrCreate(
    ['name' => 'Hendra Setia Permana'],
    ['position' => 'RGM', 'status' => 'Aktif', 'homebase' => 'Purwokerto']
);
$budi = Employee::firstOrCreate(
    ['name' => 'Budi Santoso'],
    ['position' => 'ASM', 'status' => 'Aktif', 'homebase' => 'Cirebon']
);
$johan = Employee::firstOrCreate(
    ['name' => 'JOHAN ARIF'],
    ['position' => 'ASM', 'status' => 'Aktif', 'homebase' => 'Cirebon']
);

$branchPurwokerto = Branch::firstOrCreate(
    ['name' => 'REALME PURWOKERTO'],
    ['brand' => 'REALME', 'reffnote' => 'REALME PURWOKERTO', 'city' => 'Purwokerto']
);
$branchCirebon = Branch::firstOrCreate(
    ['name' => 'TECNO CIREBON'],
    ['brand' => 'TECNO', 'reffnote' => 'TECNO CIREBON', 'city' => 'Cirebon']
);

// 1. Sample Klaim BBM
Claim::create([
    'employee_id' => $johan->id,
    'claim_category' => 'bbm',
    'claim_date' => '2026-08-20',
    '_uid' => 'UID-BBM-001',
    'branch_id' => $branchCirebon->id,
    'brand' => 'TECNO',
    'reffnote' => 'TECNO CIREBON',
    'city' => 'Cirebon',
    'vehicle_type' => 'Mobil',
    'fuel_type' => 'Pertamax',
    'fuel_base_amount' => 50000,
    'fuel_pertamax_price' => 12300,
    'fuel_pertalite_price' => 10000,
    'fuel_liters' => 4.065,
    'fuel_extra_amount' => 5000,
    'amount' => 45650,
    'note' => 'SPBU 44.531 Tuparev Cirebon',
    'approval_status' => 'DISETUJUI',
    'disbursement_status' => 'Sudah Dicairkan',
    'disbursed_at' => now(),
]);

// 2. Sample Klaim Entertain
Claim::create([
    'employee_id' => $hendra->id,
    'claim_category' => 'transport_entertain',
    'claim_date' => '2026-08-22',
    '_uid' => 'UID-ENT-002',
    'branch_id' => $branchPurwokerto->id,
    'brand' => 'REALME',
    'reffnote' => 'REALME PURWOKERTO',
    'city' => 'Purwokerto',
    'entertain_subtype' => 'Makan',
    'amount' => 190000,
    'purpose' => 'Meetup owner dealer mitra Purwokerto',
    'note' => 'Kafe Tomoro Purwokerto',
    'approval_status' => 'DISETUJUI',
    'disbursement_status' => 'Sudah Dicairkan',
    'disbursed_at' => now(),
]);

// 3. Sample Klaim Perdin
Claim::create([
    'employee_id' => $hendra->id,
    'claim_category' => 'perdin',
    'is_perdin' => true,
    'claim_date' => '2026-08-24',
    '_uid' => 'UID-PDN-003',
    'branch_id' => $branchCirebon->id,
    'homebase' => 'Purwokerto',
    'destination_city' => 'Cirebon',
    'distance_km' => 140,
    'days_count' => 3,
    'nights_count' => 2,
    'purpose' => 'VISIT DEALER & MONITORING CABANG',
    'meal_allowance' => 300000,
    'lodging_allowance' => 600000,
    'toll_cost' => 250000,
    'fuel_cost' => 300000,
    'amount' => 1450000,
    'approval_status' => 'ACC_PAK_JEJEN',
    'disbursement_status' => 'Belum Dicairkan',
]);

// 4. Sample Klaim Transportasi
Claim::create([
    'employee_id' => $budi->id,
    'claim_category' => 'transport_entertain',
    'claim_date' => '2026-08-25',
    '_uid' => 'UID-TRP-004',
    'branch_id' => $branchCirebon->id,
    'city' => 'Cirebon',
    'amount' => 175000,
    'items' => [
        [
            'claim_type' => 'Tol / Parkir',
            'purpose' => 'Tol Operasional Visit Area Majalengka',
            'note' => 'Gerbang Tol Kertajati',
            'amount' => 175000,
        ]
    ],
    'purpose' => 'Tol Operasional Visit Area Majalengka',
    'approval_status' => 'ACC_RGM',
    'disbursement_status' => 'Belum Dicairkan',
]);

echo "Sample data klaim operasional berhasil di-seed!\n";

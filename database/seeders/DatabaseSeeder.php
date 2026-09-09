<?php

namespace Database\Seeders;

use App\Models\AttendanceDoc;
use App\Models\AttendancePart;
use App\Models\Branch;
use App\Models\BudgetType;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Master Roles (Sesuai Ketentuan Biaya Operasional & Plafon)
        $adminRole = Role::updateOrCreate(
            ['code' => 'ADMIN'],
            [
                'name' => 'Administrator',
                'description' => 'Administrator Sistem Rekap Klaim',
                'entertain_budget' => 0,
                'operational_budget' => 0,
                'status' => 'Aktif',
            ]
        );

        $rgmRole = Role::updateOrCreate(
            ['code' => 'RGM'],
            [
                'name' => 'Revenue Growth Management (RGM)',
                'description' => 'Regional General Manager / RGM',
                'entertain_budget' => 2400000,
                'operational_budget' => 3000000,
                'meal_allowance_per_day' => 100000,
                'lodging_allowance_per_night' => 300000,
                'toll_allowance' => 500000,
                'fuel_budget_default' => 0,
                'car_rental_budget_default' => 0,
                'service_car_budget_quarterly' => 2000000,
                'service_motor_budget_quarterly' => 500000,
                'status' => 'Aktif',
            ]
        );

        $asmRole = Role::updateOrCreate(
            ['code' => 'ASM'],
            [
                'name' => 'Area Sales Manager (ASM)',
                'description' => 'Area Sales Manager Cabang',
                'entertain_budget' => 1500000,
                'operational_budget' => 2000000,
                'meal_allowance_per_day' => 75000,
                'lodging_allowance_per_night' => 250000,
                'toll_allowance' => 500000,
                'fuel_budget_default' => 0,
                'car_rental_budget_default' => 0,
                'service_car_budget_quarterly' => 2000000,
                'service_motor_budget_quarterly' => 500000,
                'status' => 'Aktif',
            ]
        );

        $ascRole = Role::updateOrCreate(
            ['code' => 'ASC'],
            [
                'name' => 'Area Sales Coordinator (ASC)',
                'description' => 'Area Sales Coordinator',
                'entertain_budget' => 500000,
                'operational_budget' => 1000000,
                'meal_allowance_per_day' => 50000,
                'lodging_allowance_per_night' => 175000, // 150k - 200k
                'toll_allowance' => 300000,
                'status' => 'Aktif',
            ]
        );

        $dsfRole = Role::updateOrCreate(
            ['code' => 'DSF'],
            [
                'name' => 'Distribution Sales Force (DSF)',
                'description' => 'Distribution Sales Force / Sales Lapangan',
                'entertain_budget' => 300000,
                'operational_budget' => 800000,
                'meal_allowance_per_day' => 35000,
                'lodging_allowance_per_night' => 150000,
                'toll_allowance' => 200000,
                'status' => 'Aktif',
            ]
        );

        $csoRole = Role::updateOrCreate(
            ['code' => 'CSO'],
            [
                'name' => 'Chief Sales Officer (CSO)',
                'description' => 'Chief Sales Officer',
                'entertain_budget' => 3000000,
                'operational_budget' => 5000000,
                'meal_allowance_per_day' => 120000,
                'lodging_allowance_per_night' => 400000,
                'toll_allowance' => 500000,
                'status' => 'Aktif',
            ]
        );

        $jejenRole = Role::updateOrCreate(
            ['code' => 'JEJEN'],
            [
                'name' => 'Pak Jejen (Management / Final Approver)',
                'description' => 'Management / Final Approval Klaim Operasional',
                'entertain_budget' => 0,
                'operational_budget' => 0,
                'status' => 'Aktif',
            ]
        );

        // 2. Positions (Jabatan legacy)
        $rgmPos = Position::updateOrCreate(
            ['name' => 'RGM'],
            [
                'entertain_budget' => 2400000,
                'operational_budget' => 3000000,
                'description' => 'Regional General Manager',
            ]
        );

        $asmPos = Position::updateOrCreate(
            ['name' => 'ASM'],
            [
                'entertain_budget' => 1500000,
                'operational_budget' => 2000000,
                'description' => 'Area Sales Manager',
            ]
        );

        $staffPos = Position::updateOrCreate(
            ['name' => 'Staff Operasional'],
            [
                'entertain_budget' => 500000,
                'operational_budget' => 1000000,
                'description' => 'Staff Operasional & Administrasi',
            ]
        );

        // 3. Master Budget Types
        BudgetType::updateOrCreate(
            ['code' => 'ENTERTAIN'],
            ['name' => 'Entertain', 'amount' => 2400000, 'description' => 'Biaya jamuan makan / meeting atau karangan bunga & kue ultah.', 'status' => 'Aktif']
        );
        BudgetType::updateOrCreate(
            ['code' => 'BBM'],
            ['name' => 'BBM', 'amount' => 1000000, 'description' => 'Biaya bahan bakar minyak untuk operasional.', 'status' => 'Aktif']
        );
        BudgetType::updateOrCreate(
            ['code' => 'TRANSPORTASI'],
            ['name' => 'Transportasi', 'amount' => 1000000, 'description' => 'Biaya transportasi, tol, dan tiket.', 'status' => 'Aktif']
        );
        BudgetType::updateOrCreate(
            ['code' => 'PERDIN'],
            ['name' => 'Perjalanan Dinas', 'amount' => 2000000, 'description' => 'Uang makan, penginapan, dan akomodasi dinas luar kota (min. 80km).', 'status' => 'Aktif']
        );
        BudgetType::updateOrCreate(
            ['code' => 'SERVICE_MOTOR'],
            ['name' => 'Service Motor', 'amount' => 500000, 'description' => 'Pemeliharaan dan service kendaraan operasional.', 'status' => 'Aktif']
        );

        // 4. Employees
        $hendra = Employee::updateOrCreate(
            ['name' => 'Hendra Setia Permana'],
            [
                'custom_id' => 'EMP-RGM01',
                'role_id' => $rgmRole->id,
                'position_id' => $rgmPos->id,
                'position' => 'RGM',
                'homebase' => 'Purwokerto',
                'phone' => '081234567890',
                'email' => 'hendra@company.com',
                'status' => 'Aktif',
            ]
        );

        $budi = Employee::updateOrCreate(
            ['name' => 'Budi Santoso'],
            [
                'custom_id' => 'EMP-ASM01',
                'role_id' => $asmRole->id,
                'position_id' => $asmPos->id,
                'position' => 'ASM',
                'homebase' => 'Purwokerto',
                'phone' => '081298765432',
                'email' => 'budi.santoso@company.com',
                'status' => 'Aktif',
            ]
        );

        $siti = Employee::updateOrCreate(
            ['name' => 'Siti Rahma'],
            [
                'custom_id' => 'EMP-STF01',
                'role_id' => $dsfRole->id,
                'position_id' => $staffPos->id,
                'position' => 'Staff Operasional',
                'homebase' => 'Purwokerto',
                'phone' => '081311223344',
                'email' => 'siti.rahma@company.com',
                'status' => 'Aktif',
            ]
        );

        // 5. Users with Customizable Alphanumeric IDs
        User::updateOrCreate(
            ['email' => 'admin@salesklaim.com'],
            [
                'custom_id' => 'ADM-001',
                'name' => 'Administrator Klaim',
                'role_id' => $adminRole->id,
                'homebase' => 'Purwokerto',
                'password' => Hash::make('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'rgm@salesklaim.com'],
            [
                'custom_id' => 'RGM-001',
                'name' => 'Hendra Setia Permana (RGM)',
                'role_id' => $rgmRole->id,
                'employee_id' => $hendra->id,
                'homebase' => 'Purwokerto',
                'password' => Hash::make('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'asm@salesklaim.com'],
            [
                'custom_id' => 'ASM-001',
                'name' => 'Budi Santoso (ASM)',
                'role_id' => $asmRole->id,
                'employee_id' => $budi->id,
                'homebase' => 'Purwokerto',
                'password' => Hash::make('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'jejen@salesklaim.com'],
            [
                'custom_id' => 'MGT-001',
                'name' => 'Pak Jejen (Management)',
                'role_id' => $jejenRole->id,
                'homebase' => 'Jakarta',
                'password' => Hash::make('password'),
            ]
        );

        // 6. Branches
        $branchPwt = Branch::updateOrCreate(
            ['name' => 'REALME PURWOKERTO'],
            [
                'brand' => 'REALME',
                'reffnote' => 'REALME PURWOKERTO',
                'city' => 'Purwokerto',
                'status' => 'Aktif',
            ]
        );

        $branchSmg = Branch::updateOrCreate(
            ['name' => 'REALME SEMARANG'],
            [
                'brand' => 'REALME',
                'reffnote' => 'REALME SEMARANG',
                'city' => 'Semarang',
                'status' => 'Aktif',
            ]
        );

        Branch::updateOrCreate(
            ['name' => 'REALME TEGAL'],
            [
                'brand' => 'REALME',
                'reffnote' => 'REALME TEGAL',
                'city' => 'Tegal',
                'status' => 'Aktif',
            ]
        );

        // 7. Sample Claim 1: RGM Regular Klaim (Entertain Makan & BBM)
        Claim::create([
            'employee_id' => $hendra->id,
            'claim_date' => '2026-08-26',
            'branch_id' => $branchPwt->id,
            'brand' => $branchPwt->brand,
            'reffnote' => $branchPwt->reffnote,
            'city' => 'Cilacap',
            'approval_status' => 'ACC_PAK_JEJEN',
            'disbursement_status' => 'Sudah Dicairkan',
            'disbursed_at' => '2026-08-28 10:00:00',
            'items' => [
                [
                    'claim_type' => 'Entertain',
                    'entertain_subtype' => 'Makan',
                    'purpose' => 'Meeting dengan ASM Purwokerto',
                    'note' => 'Warung Makan Sumber Rejeki Surabaya',
                    'city' => 'Cilacap',
                    'amount' => 87000,
                ],
                [
                    'claim_type' => 'Entertain',
                    'entertain_subtype' => 'Makan',
                    'purpose' => 'Meetup dengan owner dealer',
                    'note' => 'Kafe Tomoro Cilacap',
                    'city' => 'Cilacap',
                    'amount' => 190000,
                ],
                [
                    'claim_type' => 'BBM',
                    'purpose' => 'BBM Perjalanan Dinas Cilacap',
                    'note' => 'SPBU 44.532.01 Cilacap',
                    'city' => 'Cilacap',
                    'amount' => 100000,
                ],
            ],
        ]);

        // Sample Claim 2: Perjalanan Dinas ASM (Jarak 110 KM ke Majenang)
        Claim::create([
            'employee_id' => $budi->id,
            'claim_date' => '2026-08-27',
            'branch_id' => $branchPwt->id,
            'brand' => $branchPwt->brand,
            'reffnote' => $branchPwt->reffnote,
            'city' => 'Majenang',
            'is_perdin' => true,
            'homebase' => 'Purwokerto',
            'destination_city' => 'Majenang',
            'distance_km' => 110,
            'days_count' => 2,
            'nights_count' => 1,
            'meal_allowance' => 150000, // 2 x 75k
            'lodging_allowance' => 250000, // 1 x 250k
            'toll_cost' => 100000,
            'fuel_cost' => 200000,
            'car_rental_cost' => 0,
            'service_cost' => 0,
            'approval_status' => 'ACC_RGM',
            'disbursement_status' => 'Belum Dicairkan',
        ]);

        // Sample Claim 3: Entertain Lainnya (Karangan Bunga)
        Claim::create([
            'employee_id' => $siti->id,
            'claim_date' => '2026-08-28',
            'branch_id' => $branchPwt->id,
            'brand' => $branchPwt->brand,
            'reffnote' => $branchPwt->reffnote,
            'city' => 'Purwokerto',
            'approval_status' => 'ACC_ASM',
            'disbursement_status' => 'Belum Dicairkan',
            'items' => [
                [
                    'claim_type' => 'Entertain',
                    'entertain_subtype' => 'Lainnya',
                    'purpose' => 'Karangan Bunga Grand Opening Dealer Baru',
                    'note' => 'Florist Purwokerto Indah',
                    'city' => 'Purwokerto',
                    'amount' => 350000,
                ],
            ],
        ]);

        // 8. Sample Attendance Doc
        $doc = AttendanceDoc::create([
            'employee_id' => $hendra->id,
            'purpose' => 'MEETING DENGAN ASM PURWOKERTO',
            'date' => '2026-08-26',
            'place' => 'WARUNG MAKAN SUMBER REJEKI SURABAYA',
            'notes' => 'Evaluasi target sales bulan Agustus dan strategi Q4.',
        ]);

        AttendancePart::create([
            'attendance_doc_id' => $doc->id,
            'name' => 'Hendra Setia Permana',
            'position' => 'RGM',
        ]);
        AttendancePart::create([
            'attendance_doc_id' => $doc->id,
            'name' => 'Wahyu Prasetyo',
            'position' => 'ASM Purwokerto',
        ]);
        AttendancePart::create([
            'attendance_doc_id' => $doc->id,
            'name' => 'Eko Widodo',
            'position' => 'Team Leader',
        ]);
    }
}

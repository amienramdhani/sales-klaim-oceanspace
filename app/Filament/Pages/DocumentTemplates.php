<?php

namespace App\Filament\Pages;

use App\Models\AttendanceDoc;
use App\Models\AttendancePart;
use App\Models\Branch;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\Position;
use App\Services\AttendanceWordExportService;
use App\Services\BbmBeritaAcaraWordExportService;
use App\Services\ClaimExcelExportService;
use Filament\Pages\Page;

class DocumentTemplates extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'DOKUMEN';

    protected static ?string $navigationLabel = 'Template Dokumen';

    protected static ?string $title = 'Template & Panduan Dokumen Klaim';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.document-templates';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ($user->isAdmin() || $user->isSuperAdmin());
    }

    public function downloadSampleExcel()
    {
        $claim = Claim::with('employee.positionModel', 'branch')->first();

        if (! $claim) {
            $pos = Position::firstOrCreate(['name' => 'RGM'], ['entertain_budget' => 2400000]);
            $hendra = Employee::firstOrCreate(
                ['name' => 'Hendra Setia Permana'],
                ['position_id' => $pos->id, 'position' => 'RGM', 'status' => 'Aktif']
            );
            $branch = Branch::firstOrCreate(
                ['name' => 'REALME PURWOKERTO'],
                ['brand' => 'REALME', 'reffnote' => 'REALME PURWOKERTO', 'city' => 'Purwokerto']
            );

            $claim = Claim::create([
                'employee_id' => $hendra->id,
                'claim_date' => now(),
                'branch_id' => $branch->id,
                'brand' => 'REALME',
                'reffnote' => 'REALME PURWOKERTO',
                'city' => 'Cilacap',
                'items' => [
                    [
                        'claim_type' => 'Entertain',
                        'purpose' => 'meetup dengan owner dealer',
                        'note' => 'Kafe Tomoro',
                        'city' => 'Cilacap',
                        'amount' => 190000,
                    ],
                    [
                        'claim_type' => 'BBM',
                        'purpose' => 'BBM Perjalanan Dinas',
                        'note' => 'SPBU 44.532.01 Cilacap',
                        'city' => 'Cilacap',
                        'amount' => 100000,
                    ],
                ],
            ]);
        }

        return app(ClaimExcelExportService::class)->exportSingleClaim($claim);
    }

    public function downloadSamplePerdin()
    {
        $perdinClaim = Claim::where('is_perdin', true)->with('employee')->first();
        return app(ClaimExcelExportService::class)->exportPerdinForm($perdinClaim);
    }

    public function downloadBlankWordAttendance()
    {
        $dummyDoc = new AttendanceDoc([
            'purpose' => 'MEETING / PERTEMUAN CABANG',
            'date' => now(),
            'place' => 'WARUNG MAKAN SUMBER REJEKI SURABAYA',
        ]);
        $dummyDoc->setRelation('participants', collect([
            new AttendancePart(['name' => 'Hendra Setia Permana', 'position' => 'RGM']),
            new AttendancePart(['name' => 'Wahyu Prasetyo', 'position' => 'ASM Purwokerto']),
            new AttendancePart(['name' => 'Eko Widodo', 'position' => 'Team Leader']),
        ]));

        return app(AttendanceWordExportService::class)->export($dummyDoc);
    }

    public function downloadBlankoBeritaAcaraBbm()
    {
        $dummyClaim = new Claim([
            'claim_date' => now(),
            'ba_phone' => '+62 821-7510-0062',
            'ba_division' => 'Distribusi Sales - TECNO',
            'ba_approver_1' => 'Agus Supangat',
            'ba_approver_2' => 'Alb. Maria Adi Nugroho',
            'ba_approver_3' => 'Yoga Prima Hadi',
        ]);

        return app(BbmBeritaAcaraWordExportService::class)->export($dummyClaim, [
            'name' => 'JOHAN ARIF',
            'position' => 'ASM',
            'phone' => '+62 821-7510-0062',
            'division' => 'Distribusi Sales - TECNO',
        ]);
    }

    public function downloadBlankoFormKlaimBbmWord()
    {
        $dummyClaim = new Claim([
            'claim_date' => now(),
            'claim_category' => 'bbm',
            '_uid' => 'UID-' . date('Ymd-His'),
            'vehicle_type' => 'Mobil',
            'fuel_type' => 'Pertalite',
            'note' => 'SPBU 44.531 Tuparev Cirebon',
            'fuel_start_km' => 12540,
            'fuel_base_amount' => 205000,
            'amount' => 205000,
            'items' => [
                [
                    'receipt_date' => now()->toDateString(),
                    'vehicle_type' => 'Mobil',
                    'fuel_type' => 'Pertalite',
                    'note' => 'SPBU 44.531 Tuparev Cirebon',
                    'fuel_start_km' => 12540,
                    'fuel_base_amount' => 205000,
                    'fuel_liters' => 20.5,
                    'amount' => 205000,
                ],
            ],
            'approval_status' => 'DISETUJUI',
            'disbursement_status' => 'Sudah Dicairkan',
        ]);

        return app(\App\Services\BbmClaimWordExportService::class)->export($dummyClaim, [
            'name' => 'RIVENJER BILLY KAPAHANG',
            'position' => 'ASM',
        ]);
    }
}

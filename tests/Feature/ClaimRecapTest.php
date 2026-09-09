<?php

namespace Tests\Feature;

use App\Models\AttendanceDoc;
use App\Models\AttendancePart;
use App\Models\Branch;
use App\Models\BudgetType;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\AttendanceWordExportService;
use App\Services\ClaimExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ClaimRecapTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_types_with_amount(): void
    {
        $entertain = BudgetType::create([
            'name' => 'Entertain',
            'code' => 'ENTERTAIN',
            'amount' => 2400000,
            'status' => 'Aktif',
        ]);

        $this->assertEquals('Entertain', $entertain->name);
        $this->assertEquals(2400000, (float)$entertain->amount);
    }

    public function test_employee_with_signature_and_expense_recap(): void
    {
        $pos = Position::create(['name' => 'RGM', 'entertain_budget' => 2400000, 'operational_budget' => 3000000]);
        $emp = Employee::create([
            'position_id' => $pos->id,
            'name' => 'Hendra Setia Permana',
            'position' => 'RGM',
            'signature_image' => 'signatures/sample.png',
            'status' => 'Aktif',
        ]);

        $claim = Claim::create([
            'employee_id' => $emp->id,
            'claim_date' => '2026-08-26',
            'items' => [
                [
                    'claim_type' => 'Entertain',
                    'purpose' => 'meetup dengan owner dealer',
                    'note' => 'Kafe Tomoro',
                    'city' => 'Cilacap',
                    'amount' => 190000,
                ],
            ],
        ]);

        $this->assertEquals(190000, (float)$emp->total_expense_used);
        $this->assertEquals(5400000 - 190000, (float)$emp->remaining_budget);
    }

    public function test_export_entertain_budget_recap_excel(): void
    {
        $pos = Position::create(['name' => 'RGM', 'entertain_budget' => 2400000]);
        $emp = Employee::create(['name' => 'Hendra Setia Permana', 'position_id' => $pos->id, 'position' => 'RGM']);
        Claim::create([
            'employee_id' => $emp->id,
            'claim_date' => now(),
            'items' => [
                ['claim_type' => 'Entertain', 'purpose' => 'Meeting', 'amount' => 190000],
            ],
        ]);

        $excelService = app(ClaimExcelExportService::class);
        $response = $excelService->exportEntertainBudgetRecap(Employee::with(['positionModel', 'claims'])->get());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));
    }

    public function test_paired_claim_items_city_and_single_export(): void
    {
        $position = Position::create([
            'name' => 'RGM',
            'entertain_budget' => 2400000,
            'operational_budget' => 3000000,
        ]);

        $employee = Employee::create([
            'position_id' => $position->id,
            'name' => 'Hendra Setia Permana',
            'position' => 'RGM',
            'status' => 'Aktif',
        ]);

        $branch = Branch::create([
            'name' => 'REALME PURWOKERTO',
            'brand' => 'REALME',
            'reffnote' => 'REALME PURWOKERTO',
            'city' => 'Purwokerto',
            'status' => 'Aktif',
        ]);

        $claim = Claim::create([
            'employee_id' => $employee->id,
            'claim_date' => '2026-08-26',
            'branch_id' => $branch->id,
            'brand' => $branch->brand,
            'reffnote' => $branch->reffnote,
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
                    'purpose' => 'BBM Perjalanan Cilacap',
                    'note' => 'SPBU 44.532.01 Cilacap',
                    'city' => 'Purwokerto',
                    'amount' => 100000,
                ],
            ],
        ]);

        // Total auto calculated: 190000 + 100000 = 290000
        $this->assertEquals(290000, (float)$claim->amount);

        // Test single claim export for that person
        $excelService = app(ClaimExcelExportService::class);
        $response = $excelService->exportSingleClaim($claim);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));
    }

    public function test_word_meeting_attendance_with_aptos_and_no_position_col(): void
    {
        $position = Position::create(['name' => 'RGM', 'entertain_budget' => 2400000]);
        $employee = Employee::create([
            'position_id' => $position->id,
            'name' => 'Hendra Setia Permana',
            'position' => 'RGM',
            'signature_image' => 'signatures/sample.png',
        ]);

        $doc = AttendanceDoc::create([
            'employee_id' => $employee->id,
            'purpose' => 'MEETING DENGAN ASM PURWOKERTO',
            'date' => '2026-08-26',
            'place' => 'WARUNG MAKAN SUMBER REJEKI SURABAYA',
        ]);

        AttendancePart::create([
            'attendance_doc_id' => $doc->id,
            'name' => 'Hendra Setia Permana',
        ]);
        AttendancePart::create([
            'attendance_doc_id' => $doc->id,
            'name' => 'Wahyu Prasetyo',
        ]);

        $wordService = app(AttendanceWordExportService::class);
        $response = $wordService->export($doc);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $response->headers->get('content-type'));

        // Verify clean XML structure
        $filePath = $response->getFile()->getPathname();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($filePath));
        $xml = $zip->getFromName('word/document.xml');
        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('DAFTAR HADIR MEETING', $xml);
        $this->assertStringContainsString('Aptos', $xml);
        $this->assertStringNotContainsString('Jabatan / Instansi', $xml);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $zip->close();
    }

    public function test_file_deletion_on_record_delete(): void
    {
        Storage::fake('public');

        $file1 = UploadedFile::fake()->image('receipt.jpg');
        $path1 = $file1->store('claim-photos', 'public');
        $this->assertTrue(Storage::disk('public')->exists($path1));

        $claim = Claim::create([
            'claim_date' => now(),
            'claim_type' => ['Entertain'],
            'purpose' => ['Meeting'],
            'amount' => 50000,
            'photos' => [$path1],
        ]);

        $claim->delete();
        $this->assertFalse(Storage::disk('public')->exists($path1));

        // Test signature deletion on employee delete
        $sig = UploadedFile::fake()->image('sig.png');
        $sigPath = $sig->store('signatures', 'public');
        $this->assertTrue(Storage::disk('public')->exists($sigPath));

        $emp = Employee::create([
            'name' => 'Test Employee',
            'position' => 'Staff',
            'signature_image' => $sigPath,
        ]);

        $emp->delete();
        $this->assertFalse(Storage::disk('public')->exists($sigPath));
    }

    public function test_admin_panel_all_resources_access_and_expense_recap(): void
    {
        $user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        $pos = Position::create(['name' => 'RGM', 'entertain_budget' => 2400000]);
        $emp = Employee::create(['name' => 'Hendra Setia Permana', 'position_id' => $pos->id, 'position' => 'RGM']);
        Claim::create(['employee_id' => $emp->id, 'claim_date' => now(), 'claim_type' => ['Entertain'], 'purpose' => ['Meeting'], 'amount' => 190000]);

        $this->actingAs($user)->get('/admin')->assertStatus(200);
        $this->actingAs($user)->get('/admin/claims')->assertStatus(200);
        $this->actingAs($user)->get('/admin/user-expense-reports')->assertStatus(200);
        $this->actingAs($user)->get('/admin/attendance-docs')->assertStatus(200);
        $this->actingAs($user)->get('/admin/budget-types')->assertStatus(200);
        $this->actingAs($user)->get('/admin/positions')->assertStatus(200);
        $this->actingAs($user)->get('/admin/employees')->assertStatus(200);
        $this->actingAs($user)->get('/admin/branches')->assertStatus(200);
        $this->actingAs($user)->get('/admin/document-templates')->assertStatus(200);
    }
}

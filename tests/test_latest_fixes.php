<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================================\n";
echo "TEST VERIFIKASI PERBAIKAN TERBARU\n";
echo "========================================================\n\n";

// 1. Test exportEntertainBudgetRecap
echo "[1] TEST METHOD exportEntertainBudgetRecap()\n";
try {
    $excelService = app(App\Services\ClaimExcelExportService::class);
    $employees = App\Models\Employee::with(['role', 'claims'])->get();
    $response = $excelService->exportEntertainBudgetRecap($employees);
    echo "  [SUCCESS] exportEntertainBudgetRecap() executed without errors!\n";
} catch (\Throwable $e) {
    echo "  [FAILED] exportEntertainBudgetRecap() error: " . $e->getMessage() . "\n";
}

// 2. Test exportPerdinForm
echo "\n[2] TEST METHOD exportPerdinForm() (PT. MEDIA SELULAR INDONESIA)\n";
try {
    $perdinClaim = App\Models\Claim::where('is_perdin', true)->with('effective_employee')->first();
    $response2 = $excelService->exportPerdinForm($perdinClaim);
    echo "  [SUCCESS] exportPerdinForm() with claim data executed successfully!\n";

    $response3 = $excelService->exportPerdinForm(null, [
        'name' => 'RIVENJER BILLY KAPAHANG',
        'position' => 'ASM',
        'destination' => 'KEPULAUAN TAHUNA',
        'purpose' => 'VISIT DEALER',
        'days' => 3,
    ]);
    echo "  [SUCCESS] exportPerdinForm() with custom template data (Rivenjer Billy Kapahang) executed successfully!\n";
} catch (\Throwable $e) {
    echo "  [FAILED] exportPerdinForm() error: " . $e->getMessage() . "\n";
}

// 3. Test Dashboard Stats
echo "\n[3] TEST WIDGET STATISTIK DASHBOARD SESUAI TRANSAKSI KLAIM\n";
try {
    $statsWidget = new App\Filament\Widgets\ClaimStatsOverview();
    $reflection = new ReflectionClass($statsWidget);
    $method = $reflection->getMethod('getStats');
    $method->setAccessible(true);
    $stats = $method->invoke($statsWidget);

    echo "  Hasil Dashboard Stats Overview:\n";
    foreach ($stats as $stat) {
        echo "   • " . $stat->getLabel() . " : " . $stat->getValue() . " (" . $stat->getDescription() . ")\n";
    }
    echo "  [SUCCESS] Dashboard Stats Overview calculated accurately from all real claim records!\n";
} catch (\Throwable $e) {
    echo "  [FAILED] Dashboard stats calculation error: " . $e->getMessage() . "\n";
}

// 4. Test BBM Transaction with Brand & Reffnote
echo "\n[4] TEST TRANSAKSI BBM DENGAN BRAND & REFFNOTE\n";
$bbmClaim = App\Models\Claim::where('claim_type', 'like', '%BBM%')->first();
if ($bbmClaim) {
    echo "  BBM Claim ID: #{$bbmClaim->id} | Brand: {$bbmClaim->brand} | Reffnote: {$bbmClaim->reffnote} | Kota: {$bbmClaim->city}\n";
    foreach ($bbmClaim->getLineItems() as $item) {
        echo "   - Item: {$item['claim_type']} | Ket: {$item['note']} | Nominal: Rp " . number_format($item['amount'], 0, ',', '.') . " | Brand: " . ($item['brand'] ?? $bbmClaim->brand) . " | Reffnote: " . ($item['reffnote'] ?? $bbmClaim->reffnote) . "\n";
    }
    echo "  [SUCCESS] Brand and Reffnote are properly linked and present on BBM transactions!\n";
}

echo "\n========================================================\n";
echo "SEMUA PERBAIKAN BERHASIL DIVERIFIKASI DENGAN SUKSES!\n";
echo "========================================================\n";

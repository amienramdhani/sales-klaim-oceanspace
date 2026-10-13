<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 1. Checking filament-mobile.css for Navbar & Card System ===\n";
$css = file_get_contents('public/css/filament-mobile.css');

$cssChecks = [
    'No aggressive body:has([role="dialog"])' => !str_contains($css, 'body:has([role="dialog"])'),
    'No aggressive body:has(.fi-modal-window)' => !str_contains($css, 'body:has(.fi-modal-window)'),
    'Clean modal open hide rule' => str_contains($css, 'body:has(.fi-modal.fi-modal-open) .mobile-bottom-nav'),
    'Card base flex wrap' => str_contains($css, 'tr.fi-ta-row') && str_contains($css, 'display: flex !important;'),
    'Row 1 Category/Type badge styling' => str_contains($css, '.fi-table-cell-claim-type-string') && str_contains($css, '.fi-table-cell-category'),
    'Row 1 Date styling' => str_contains($css, '.fi-table-cell-date'),
    'Row 2 Subject / Employee name' => str_contains($css, 'td[class*="effective-employee"]'),
    'Row 3 Purpose / Note / Location' => str_contains($css, '.fi-table-cell-purpose-string') && str_contains($css, '.fi-table-cell-place'),
    'Row 4 Nominal & Estimated amount' => str_contains($css, '.fi-table-cell-estimated-amount') && str_contains($css, 'color: #059669 !important;'),
    'Row 4 Status badges' => str_contains($css, '.fi-table-cell-admin-status'),
    'Dark mode card background' => str_contains($css, '.dark .fi-ta-table > tbody > tr.fi-ta-row') && str_contains($css, 'background: #1e293b !important;'),
    'Dark mode dropdown item color' => str_contains($css, '.dark .fi-dropdown-list-item.fi-color-gray'),
    'Action button warning/info styles' => str_contains($css, '.fi-btn-color-warning') && str_contains($css, '.fi-btn-color-info'),
];

$allPassed = true;
foreach ($cssChecks as $label => $pass) {
    echo ($pass ? "  [PASS] " : "  [FAIL] ") . $label . "\n";
    if (!$pass) $allPassed = false;
}

echo "\n=== 2. Checking mobile-bottom-nav.blade.php for Roles & Sidebar Toggle ===\n";
$blade = file_get_contents('resources/views/filament/components/mobile-bottom-nav.blade.php');

$bladeChecks = [
    'Uses dynamic columns grid layout' => str_contains($blade, '$user->isJejen() ? 3 : 5'),
    'Has Sidebar Toggle button' => str_contains($blade, "Alpine.store('sidebar')") && str_contains($blade, "fi-topbar-open-sidebar-btn"),
    'Role Finance defined' => str_contains($blade, '$user->isFinance()') && str_contains($blade, 'finance-bbm-claims') && str_contains($blade, 'finance-non-bbm-claims'),
    'Role Admin defined' => str_contains($blade, '$user->isAdmin()') && str_contains($blade, 'admin-claim-approvals') && str_contains($blade, 'attendance-docs'),
    'Role Head of Sales (Jejen) defined' => str_contains($blade, '$user->isJejen()') && str_contains($blade, 'jejen-claim-approvals'),
    'Role RGM defined' => str_contains($blade, '$user->isRgm()') && str_contains($blade, 'rgm-claim-approvals') && str_contains($blade, 'transport-entertain-claims'),
    'Role ASM defined' => str_contains($blade, '$user->isAsm()') && str_contains($blade, 'asm-claim-approvals') && str_contains($blade, 'transport-entertain-claims'),
    'Role Sales defined' => str_contains($blade, 'ROLE SALES / STAFF LAPANGAN') && str_contains($blade, 'perdin-claims') && str_contains($blade, 'transport-entertain-claims'),
    'Safe checkModal() logic' => str_contains($blade, 'fi-modal.fi-modal-open') && !str_contains($blade, ":not([style*=\'display: none\'])"),
];

foreach ($bladeChecks as $label => $pass) {
    echo ($pass ? "  [PASS] " : "  [FAIL] ") . $label . "\n";
    if (!$pass) $allPassed = false;
}

echo "\n=== 3. Simulating Bottom Nav Rendering for Each Role ===\n";
$roles = [
    'Admin' => \App\Models\User::where('email', 'admin@salesklaim.com')->first(),
    'Head of Sales (Jejen)' => \App\Models\User::where('email', 'jejen@msi.com')->first(),
    'RGM' => \App\Models\User::where('email', 'hendra@msi.com')->first(),
    'ASM' => \App\Models\User::where('email', 'asm@salesklaim.com')->first(),
    'Sales' => \App\Models\User::where('email', 'bima@msi.com')->first(),
    'Finance' => \App\Models\User::where('email', 'finance@salesklaim.com')->first(),
];

foreach ($roles as $roleName => $testUser) {
    if (!$testUser) {
        echo "  [SKIP] {$roleName}: User not found\n";
        continue;
    }
    auth()->login($testUser);
    try {
        $viewOutput = view('filament.components.mobile-bottom-nav')->render();
        preg_match_all('/<span class="mobile-bottom-nav-label">(.*?)<\/span>/', $viewOutput, $labels);
        $tabLabels = implode(' | ', $labels[1] ?? []);
        echo "  [OK] {$roleName} ({$testUser->name}): [{$tabLabels}]\n";
    } catch (\Throwable $e) {
        echo "  [ERR] {$roleName}: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
}

echo "\nResult: " . ($allPassed ? "ALL TESTS PASSED SUCCESSFULLY!" : "SOME TESTS FAILED!") . "\n";

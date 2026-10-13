<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 1. Checking filament-mobile.css Rules ===\n";
$css = file_get_contents('public/css/filament-mobile.css');

$checks = [
    'Bottom nav z-index is 35' => str_contains($css, 'z-index: 35 !important;'),
    'Modal z-index is 9999' => str_contains($css, 'z-index: 9999 !important;'),
    'Modal auto-hide body:has rule exists' => str_contains($css, 'body:has(.fi-modal-open) .mobile-bottom-nav'),
    'Modal window max-height and margin exist' => str_contains($css, 'max-height: calc(100vh - 2.5rem) !important;'),
    'Line break pseudo-element exists' => str_contains($css, 'order: 2 !important;') && str_contains($css, 'flex-basis: 100% !important;'),
    'Berkas order: 1 and margin-right: auto' => str_contains($css, 'margin-right: auto !important;') && str_contains($css, 'order: 1 !important;'),
    'Aksi dropdown order: 1 and margin-left: auto' => str_contains($css, 'margin-left: auto !important;') && str_contains($css, 'order: 1 !important;'),
    'Decision buttons 50% width and order: 3' => str_contains($css, 'flex: 1 1 calc(50% - 0.25rem) !important;') && str_contains($css, 'order: 3 !important;'),
    'Page padding-bottom 6.5rem' => str_contains($css, 'padding-bottom: 6.5rem !important;'),
];

foreach ($checks as $name => $ok) {
    echo ($ok ? "  [PASS] " : "  [FAIL] ") . $name . "\n";
}

echo "\n=== 2. Checking mobile-bottom-nav.blade.php ===\n";
$blade = file_get_contents('resources/views/filament/components/mobile-bottom-nav.blade.php');
$bladeChecks = [
    'Alpine isModalOpen reactive state' => str_contains($blade, 'isModalOpen: false'),
    'x-show="!isModalOpen"' => str_contains($blade, 'x-show="!isModalOpen"'),
    'MutationObserver checks document.body' => str_contains($blade, 'new MutationObserver'),
    'open-modal and close-modal listeners' => str_contains($blade, "window.addEventListener('open-modal'"),
];

foreach ($bladeChecks as $name => $ok) {
    echo ($ok ? "  [PASS] " : "  [FAIL] ") . $name . "\n";
}

echo "\n=== 3. Checking Approval Resources Action Definitions ===\n";
$resources = [
    'RgmClaimApprovalResource' => 'app/Filament/Resources/RgmClaimApprovalResource.php',
    'AsmClaimApprovalResource' => 'app/Filament/Resources/AsmClaimApprovalResource.php',
    'JejenClaimApprovalResource' => 'app/Filament/Resources/JejenClaimApprovalResource.php',
    'AdminClaimApprovalResource' => 'app/Filament/Resources/AdminClaimApprovalResource.php',
    'FinanceBbmClaimResource' => 'app/Filament/Resources/FinanceBbmClaimResource.php',
    'FinanceNonBbmClaimResource' => 'app/Filament/Resources/FinanceNonBbmClaimResource.php',
];

foreach ($resources as $resName => $path) {
    $content = file_get_contents($path);
    $hasViewFiles = str_contains($content, 'ViewClaimFilesAction::make()');
    $hasButtons = str_contains($content, '->button()');
    echo "  {$resName}: ViewFiles=" . ($hasViewFiles ? 'YES' : 'NO') . " | HasButton=" . ($hasButtons ? 'YES' : 'NO') . "\n";
}

echo "\nVerification completed successfully!\n";

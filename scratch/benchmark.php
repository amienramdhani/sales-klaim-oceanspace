<?php
$start = microtime(true);

require_once __DIR__ . '/../vendor/autoload.php';
$t1 = microtime(true);
echo "1. Autoload: " . round(($t1 - $start) * 1000, 2) . " ms\n";

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$t2 = microtime(true);
echo "2. Laravel Bootstrap: " . round(($t2 - $t1) * 1000, 2) . " ms\n";

use App\Models\User;
use App\Models\Claim;
use Illuminate\Support\Facades\DB;

DB::enableQueryLog();

// Simulate SuperAdmin loading Dashboard
$admin = User::where('email', 'admin@salesklaim.com')->first();
auth()->setUser($admin);
$t3 = microtime(true);
echo "3. Auth set user: " . round(($t3 - $t2) * 1000, 2) . " ms\n";

// Navigation badges queries
$b1 = \App\Filament\Resources\AdminClaimApprovalResource::getNavigationBadge();
$b2 = \App\Filament\Resources\JejenClaimApprovalResource::getNavigationBadge();
$b3 = \App\Filament\Resources\FinanceBbmClaimResource::getNavigationBadge();
$b4 = \App\Filament\Resources\FinanceNonBbmClaimResource::getNavigationBadge();
$b5 = \App\Filament\Resources\RgmClaimApprovalResource::getNavigationBadge();
$b6 = \App\Filament\Resources\AsmClaimApprovalResource::getNavigationBadge();
$t4 = microtime(true);
echo "4. All Navigation Badges: " . round(($t4 - $t3) * 1000, 2) . " ms\n";

// Bottom Nav view render
try {
    $view = view('filament.components.mobile-bottom-nav')->render();
} catch (\Throwable $e) {
    echo "Bottom nav error: " . $e->getMessage() . "\n";
}
$t5 = microtime(true);
echo "5. Bottom Nav render: " . round(($t5 - $t4) * 1000, 2) . " ms\n";

// Stats Overview widget
try {
    $widget = new \App\Filament\Widgets\ClaimStatsOverview();
    // call getStats via reflection
    $ref = new ReflectionMethod($widget, 'getStats');
    $ref->setAccessible(true);
    $stats = $ref->invoke($widget);
} catch (\Throwable $e) {
    echo "Widget error: " . $e->getMessage() . "\n";
}
$t6 = microtime(true);
echo "6. Stats Overview widget: " . round(($t6 - $t5) * 1000, 2) . " ms\n";

// Total queries
$queries = DB::getQueryLog();
echo "Total queries executed: " . count($queries) . "\n";
echo "Total time: " . round(($t6 - $start) * 1000, 2) . " ms\n";

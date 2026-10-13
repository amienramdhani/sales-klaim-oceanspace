<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ADMIN USERS ===\n";
foreach (App\Models\User::all() as $u) {
    if ($u->isAdmin() || $u->role_id == 1 || $u->role_id == 9 || str_contains(strtoupper($u->role_name), 'ADMIN')) {
        echo "ID: {$u->id} | Name: {$u->name} | Email: {$u->email} | role_id: {$u->role_id} | role_code: {$u->role_code} | isAdmin: " . ($u->isAdmin() ? 'YES' : 'NO') . " | isSuperAdmin: " . ($u->isSuperAdmin() ? 'YES' : 'NO') . "\n";
    }
}

echo "\n=== CHECKING CANEDIT ON SAMPLE CLAIMS ===\n";
$claims = App\Models\Claim::latest()->take(5)->get();
$adminUser = App\Models\User::where('role_id', 1)->first(); // Admin
auth()->login($adminUser);
echo "Testing as: {$adminUser->name} (Role: {$adminUser->role_code}, isAdmin: " . ($adminUser->isAdmin() ? 'YES' : 'NO') . ")\n";

foreach ($claims as $c) {
    echo "\nClaim #{$c->id} | category: {$c->claim_category} | status: {$c->approval_status} | user_id: {$c->user_id} | employee_id: {$c->employee_id}\n";
    echo "  PerdinClaimResource::canEdit: " . (App\Filament\Resources\PerdinClaimResource::canEdit($c) ? 'YES' : 'NO') . "\n";
    echo "  BbmClaimResource::canEdit: " . (App\Filament\Resources\BbmClaimResource::canEdit($c) ? 'YES' : 'NO') . "\n";
    echo "  TransportEntertainClaimResource::canEdit: " . (App\Filament\Resources\TransportEntertainClaimResource::canEdit($c) ? 'YES' : 'NO') . "\n";
    echo "  VehicleServiceClaimResource::canEdit: " . (App\Filament\Resources\VehicleServiceClaimResource::canEdit($c) ? 'YES' : 'NO') . "\n";
    echo "  ClaimResource::canEdit: " . (App\Filament\Resources\ClaimResource::canEdit($c) ? 'YES' : 'NO') . "\n";
}

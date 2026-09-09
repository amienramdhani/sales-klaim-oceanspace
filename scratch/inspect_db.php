<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Employee;
use App\Models\Claim;

echo "=== USERS ===\n";
foreach (User::all() as $u) {
    echo "User #{$u->id}: {$u->name} | role: {$u->role_code} | region: {$u->region} | managed: " . json_encode($u->managed_regions) . "\n";
}

echo "\n=== EMPLOYEES (Sample) ===\n";
foreach (Employee::limit(10)->get() as $e) {
    echo "Emp #{$e->id}: {$e->name} | pos: {$e->position} | region: {$e->region}\n";
}

echo "\n=== CLAIMS (Sample) ===\n";
foreach (Claim::with(['user', 'employee'])->latest()->limit(5)->get() as $c) {
    echo "Claim #{$c->id}: user: " . ($c->user?->name ?? 'none') . " | emp: " . ($c->employee?->name ?? 'none') . " | region: {$c->region} | status: {$c->approval_status}\n";
}

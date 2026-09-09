<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;

$claims = Claim::with(['employee', 'user'])->latest()->limit(20)->get();
echo "Total claims: " . Claim::count() . "\n";
foreach ($claims as $c) {
    echo "Claim #{$c->id} | user_id: {$c->user_id} (" . ($c->user?->name ?? 'none') . ") | emp_id: {$c->employee_id} (" . ($c->employee?->name ?? 'none') . ") | emp_homebase: " . ($c->employee?->homebase ?? 'none') . " | claim_homebase: {$c->homebase} | city: {$c->city} | cat: {$c->claim_category} | status: {$c->approval_status} | disb: {$c->disbursement_status}\n";
}

<?php
require 'd:/Work/Other/sales_klaim/vendor/autoload.php';
$app = require 'd:/Work/Other/sales_klaim/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use App\Models\Claim;
use App\Models\User;

echo "--- CLAIMS COLUMNS ---\n";
print_r(Schema::getColumnListing('claims'));

echo "\n--- USERS ---\n";
foreach (User::all() as $u) {
    echo "ID: {$u->id}, Name: {$u->name}, Email: {$u->email}, RoleID: {$u->role_id}, RoleCode: {$u->role_code}, EmpID: {$u->employee_id}\n";
}

echo "\n--- RECENT CLAIMS ---\n";
foreach (Claim::latest()->take(10)->get() as $c) {
    echo "Claim ID: {$c->id}, EmpID: {$c->employee_id}, Cat: {$c->claim_category}, Type: {$c->claim_type}, Date: {$c->claim_date}\n";
}

<?php
require 'd:/Work/Other/sales_klaim/vendor/autoload.php';
$app = require 'd:/Work/Other/sales_klaim/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Claim;

echo "=== LATEST 10 CLAIMS WITH CREATED_AT ===\n";
foreach (Claim::orderBy('id', 'desc')->take(10)->get() as $c) {
    echo "ID: {$c->id} | UID: {$c->_uid} | Cat: {$c->claim_category} | EmpID: {$c->employee_id} | CreatedAt: {$c->created_at} | Date: {$c->claim_date} | Amount: {$c->amount}\n";
}

<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = App\Models\User::where(function($q) {
    $q->where('name', 'like', '%Yoga%')
      ->orWhere('name', 'like', '%Nurbaiti%')
      ->orWhere('name', 'like', '%Thia%');
})->get();

foreach ($users as $u) {
    echo "ID: {$u->id} | Name: {$u->name} | Email: {$u->email}\n";
    echo "  role_id: {$u->role_id} | role_code: {$u->role_code} | role_name: {$u->role_name}\n";
    echo "  isFinance: " . ($u->isFinance() ? 'true' : 'false') . "\n";
    echo "  isExecutiveApprover: " . ($u->isExecutiveApprover() ? 'true' : 'false') . "\n";
    echo "  isSales: " . ($u->isSales() ? 'true' : 'false') . "\n";
    echo "  isYoga: " . ($u->isYoga() ? 'true' : 'false') . "\n";
    echo "  isNurbaiti: " . ($u->isNurbaiti() ? 'true' : 'false') . "\n";
    echo "  isThia: " . ($u->isThia() ? 'true' : 'false') . "\n";
    echo "----------------------------------------\n";
}

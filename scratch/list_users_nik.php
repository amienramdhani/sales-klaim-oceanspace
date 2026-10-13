<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::with('employee')->get(['id', 'name', 'email', 'custom_id', 'employee_id', 'role_id']);
foreach ($users as $u) {
    echo "ID: {$u->id} | Name: {$u->name} | Email: {$u->email} | User CustomID: {$u->custom_id} | Emp CustomID: " . ($u->employee?->custom_id ?? 'None') . "\n";
}

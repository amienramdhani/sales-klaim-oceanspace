<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$users = App\Models\User::all();
foreach ($users as $u) {
    if (str_starts_with($u->name, 'T') || str_starts_with($u->username ?? '', 'T')) {
        echo "ID={$u->id} | {$u->name} | {$u->email} | {$u->username}\n";
    }
}

<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\User::all() as $u) {
    echo "ID: {$u->id} | {$u->name} | {$u->email} | role: " . ($u->role?->name ?? 'NO ROLE') . " | code: {$u->role_code} | region: {$u->region} | homebase: {$u->homebase}\n";
}

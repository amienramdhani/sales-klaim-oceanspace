<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== USERS ===\n";
foreach (App\Models\User::all() as $u) {
    echo "ID: {$u->id} | Name: {$u->name} | Email: {$u->email} | NIK/CustomID: {$u->custom_id} | Role: {$u->role?->name} | Region: {$u->region} | MustChangePin: " . ($u->must_change_pin ? 'YES' : 'NO') . " | InitialPin: {$u->initial_pin}\n";
}

echo "\n=== EMPLOYEES ===\n";
foreach (App\Models\Employee::all() as $e) {
    echo "ID: {$e->id} | Name: {$e->name} | NIK: {$e->custom_id} | Email: {$e->email} | Role: {$e->role?->name} | Region: {$e->region} | Position: {$e->positionModel?->name}\n";
}

echo "\n=== CLAIMS ===\n";
foreach (App\Models\Claim::all() as $c) {
    print_r($c->getAttributes());
}

echo "\n=== USERS TABLE COLUMNS ===\n";
$cols = Illuminate\Support\Facades\DB::select('DESCRIBE users');
foreach ($cols as $col) {
    echo "{$col->Field} | {$col->Type} | Null: {$col->Null} | Key: {$col->Key} | Default: {$col->Default}\n";
}

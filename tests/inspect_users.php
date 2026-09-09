<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ROLES ===\n";
foreach (App\Models\Role::all() as $r) {
    echo "ID: {$r->id} | Name: {$r->name} | Code: {$r->code}\n";
}

echo "\n=== USERS ===\n";
foreach (App\Models\User::with('role')->get() as $u) {
    $roleName = $u->role?->name ?? 'None';
    $roleCode = $u->role?->code ?? 'None';
    echo "ID: {$u->id} | Name: {$u->name} | Email: {$u->email} | Role: {$roleName} ({$roleCode})\n";
}

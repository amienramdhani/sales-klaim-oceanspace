<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::with('role')->get()->map(function($u) {
    return [
        'id' => $u->id,
        'name' => $u->name,
        'email' => $u->email,
        'role_id' => $u->role_id,
        'role_name' => $u->role?->name,
        'role_code' => $u->role_code,
        'isSales' => $u->isSales(),
        'isAsm' => $u->isAsm(),
        'isRgm' => $u->isRgm(),
        'isAdmin' => $u->isAdmin(),
        'isFinance' => $u->isFinance(),
        'isSuperAdmin' => $u->isSuperAdmin()
    ];
});

echo json_encode($users, JSON_PRETTY_PRINT);

<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$roles = App\Models\Role::all();
foreach ($roles as $r) {
    echo "ID: {$r->id} | Name: {$r->name} | Code: {$r->code}\n";
}


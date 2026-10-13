<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$roles = App\Models\Role::all();
foreach ($roles as $r) {
    echo "ID: {$r->id} | Name: {$r->name} | Code: {$r->code}\n";
}

<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$admins = App\Models\User::all()->filter(fn($u) => $u->isAdmin() || $u->isSuperAdmin());
foreach ($admins as $a) {
    echo "ID={$a->id} | {$a->name} | {$a->email} | region={$a->region} | managed=" . json_encode($a->managed_regions) . "\n";
}

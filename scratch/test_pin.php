<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\User::all() as $u) {
    echo $u->name . ' | NIK: ' . $u->custom_id . ' | must_change_pin: ' . ($u->must_change_pin ? 'YES' : 'NO') . ' | initial_pin: ' . ($u->initial_pin ?? 'NULL') . ' | changed: ' . ($u->pin_changed_at ?? 'NULL') . "\n";
}

<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Claim::all() as $c) {
    echo "Claim #{$c->id}:\n";
    echo "  user_id: {$c->user_id} (User: " . ($c->user?->name ?? 'N/A') . ")\n";
    echo "  employee_id: {$c->employee_id} (Emp: " . ($c->employee?->name ?? 'N/A') . ")\n";
    echo "  claim region: " . var_export($c->region, true) . "\n";
    echo "  claim homebase: " . var_export($c->homebase, true) . "\n";
    echo "  claim city: " . var_export($c->city, true) . "\n";
    echo "  employee region: " . var_export($c->employee?->region, true) . "\n";
    echo "  employee homebase: " . var_export($c->employee?->homebase, true) . "\n";
    echo "  user region: " . var_export($c->user?->region, true) . "\n";
    echo "  user homebase: " . var_export($c->user?->homebase, true) . "\n";
}

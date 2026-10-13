<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$branches = App\Models\Branch::all();
echo "Total branches: " . $branches->count() . "\n";
foreach ($branches as $b) {
    echo "ID: {$b->id} | {$b->name} | brand: {$b->brand} | reffnote: {$b->reffnote} | city: " . var_export($b->city, true) . "\n";
}

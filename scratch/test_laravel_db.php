<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

for ($i = 0; $i < 5; $i++) {
    $t0 = microtime(true);
    DB::reconnect();
    DB::connection()->getPdo();
    $t1 = microtime(true);
    echo "DB Reconnect #" . ($i + 1) . ": " . round(($t1 - $t0) * 1000, 2) . " ms\n";
}

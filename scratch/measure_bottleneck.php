<?php
$steps = [];
$mark = function($name) use (&$steps) {
    static $last = null;
    $now = microtime(true);
    if ($last !== null) {
        $steps[$name] = round(($now - $last) * 1000, 2);
    }
    $last = $now;
};

$mark('start');
require_once __DIR__ . '/../vendor/autoload.php';
$mark('autoload');

$app = require_once __DIR__ . '/../bootstrap/app.php';
$mark('bootstrap_app');

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$mark('make_kernel');

$kernel->bootstrap();
$mark('bootstrap_kernel');

use Illuminate\Support\Facades\DB;
$pdo = DB::connection()->getPdo();
$mark('db_connect');

$res = DB::select('SELECT 1');
$mark('db_query_simple');

$claims = DB::select('SELECT count(*) as cnt FROM claims');
$mark('db_query_claims');

foreach ($steps as $k => $ms) {
    echo "{$k}: {$ms} ms\n";
}

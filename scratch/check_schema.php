<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = DB::select('SHOW COLUMNS FROM employees');
foreach ($cols as $c) {
    echo "{$c->Field} | {$c->Type} | Null: {$c->Null} | Default: " . var_export($c->Default, true) . PHP_EOL;
}

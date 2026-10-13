<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Claim;

$ref = new ReflectionClass(Claim::class);
foreach ($ref->getMethods() as $m) {
    if (str_contains(strtolower($m->getName()), 'effective')) {
        echo "Method: " . $m->getName() . " in " . $m->getFileName() . ":" . $m->getStartLine() . "\n";
    }
}

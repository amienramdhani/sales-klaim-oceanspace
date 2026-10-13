<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$claims = \App\Models\Claim::with('employee')->where('approval_status', 'DIAJUKAN')->get();
echo "Total DIAJUKAN claims: " . $claims->count() . "\n";
foreach ($claims as $c) {
    echo "ID: {$c->id} | Emp: " . ($c->employee?->name ?? 'NULL') . " | IsASM: " . ($c->employee?->isAsm() ? 'YES' : 'NO') . "\n";
}

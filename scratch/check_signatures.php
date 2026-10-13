<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Employees with signature:\n";
foreach (App\Models\Employee::whereNotNull('signature_image')->get() as $e) {
    echo "  {$e->id} | {$e->name} | {$e->signature_image}\n";
}

echo "\nClaims with attendance:\n";
foreach (App\Models\Claim::whereNotNull('items')->get() as $c) {
    foreach ($c->items as $it) {
        if (!empty($it['attendance_participants'])) {
            echo "Claim #{$c->id} | User: {$c->user?->name} | Subtype: " . ($it['entertain_subtype'] ?? '-') . "\n";
            echo "Participants: " . json_encode($it['attendance_participants']) . "\n";
        }
    }
}

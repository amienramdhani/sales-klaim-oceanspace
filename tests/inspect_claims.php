<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (\App\Models\Claim::all() as $c) {
    echo "ID: {$c->id} | Category: {$c->claim_category}\n";
    echo "  Photos: " . json_encode($c->photos) . "\n";
    echo "  Before: " . ($c->bbm_photo_before ?? 'NULL') . "\n";
    echo "  After: " . ($c->bbm_photo_after ?? 'NULL') . "\n";
    echo "  Combined: " . ($c->bbm_photo_combined ?? 'NULL') . "\n";
    echo "  Transfer Proof: " . ($c->transfer_proof_photo ?? 'NULL') . "\n";
    echo "  Items: " . json_encode($c->items) . "\n\n";
}

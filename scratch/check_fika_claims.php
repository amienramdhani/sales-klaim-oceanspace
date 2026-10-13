<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fika = App\Models\User::find(5);
auth()->login($fika);

$qAdmin = App\Filament\Resources\AdminClaimApprovalResource::getEloquentQuery();
echo "AdminClaimApprovalResource SQL: " . $qAdmin->toSql() . "\n";
echo "AdminClaimApprovalResource Bindings: " . json_encode($qAdmin->getBindings()) . "\n";

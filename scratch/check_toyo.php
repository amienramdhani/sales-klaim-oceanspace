<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$toyo = App\Models\User::find(22);
echo "Name: " . $toyo->name . "\n";
echo "Email: " . $toyo->email . "\n";
echo "Custom ID (NIK): " . ($toyo->custom_id ?? 'none') . "\n";
echo "Initial PIN: " . ($toyo->initial_pin ?? 'none') . "\n";
echo "Must Change PIN: " . ($toyo->must_change_pin ? 'YES' : 'NO') . "\n";


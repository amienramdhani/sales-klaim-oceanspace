<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$rgm = App\Models\User::whereHas('employee.role', fn($q) => $q->where('code', 'RGM'))->first();
if ($rgm) {
    echo "RGM User: ID=" . $rgm->id . ", Name=" . $rgm->name . ", Email=" . $rgm->email . ", Username=" . $rgm->username . PHP_EOL;
} else {
    echo "No RGM user found\n";
}

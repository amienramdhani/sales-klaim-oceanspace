<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$roleMsi = App\Models\Role::where('code', 'FINANCE_MSI')->first();
$roleCvcs = App\Models\Role::where('code', 'FINANCE_CVCS')->first();

if ($roleMsi) {
    $uMsi = App\Models\User::firstOrCreate(
        ['email' => 'finance.msi@salesklaim.com'],
        [
            'name' => 'Finance PT MSI',
            'custom_id' => 'FIN-MSI01',
            'password' => bcrypt('password'),
            'role_id' => $roleMsi->id,
            'badan_usaha' => 'PT MEDIA SELULAR INDONESIA',
            'company' => 'PT MEDIA SELULAR INDONESIA',
            'homebase' => 'Cirebon',
            'must_change_pin' => false,
            'pin_changed_at' => now(),
        ]
    );
    $uMsi->update([
        'role_id' => $roleMsi->id,
        'badan_usaha' => 'PT MEDIA SELULAR INDONESIA',
        'company' => 'PT MEDIA SELULAR INDONESIA',
    ]);
    echo "User Finance MSI ready: {$uMsi->name} ({$uMsi->email})\n";
}

if ($roleCvcs) {
    $uCvcs = App\Models\User::firstOrCreate(
        ['email' => 'finance.cvcs@salesklaim.com'],
        [
            'name' => 'Finance CV Complete Selular',
            'custom_id' => 'FIN-CVCS01',
            'password' => bcrypt('password'),
            'role_id' => $roleCvcs->id,
            'badan_usaha' => 'CV COMPLETE SELULAR',
            'company' => 'CV COMPLETE SELULAR',
            'homebase' => 'Cirebon',
            'must_change_pin' => false,
            'pin_changed_at' => now(),
        ]
    );
    $uCvcs->update([
        'role_id' => $roleCvcs->id,
        'badan_usaha' => 'CV COMPLETE SELULAR',
        'company' => 'CV COMPLETE SELULAR',
    ]);
    echo "User Finance CVCS ready: {$uCvcs->name} ({$uCvcs->email})\n";
}

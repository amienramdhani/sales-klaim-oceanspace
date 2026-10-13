<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$rolesToAdd = [
    [
        'name' => 'Supervisor (SPV)',
        'code' => 'SPV',
        'description' => 'Supervisor operasional lapangan / sales',
        'status' => 'Aktif',
    ],
    [
        'name' => 'Head of Sales',
        'code' => 'HOS',
        'description' => 'Head of Sales / Atasan RGM untuk approval perdin dan klaim operasional',
        'status' => 'Aktif',
    ],
    [
        'name' => 'Finance PT Media Selular Indonesia',
        'code' => 'FINANCE_MSI',
        'description' => 'Finance pencairan dan verifikasi nota balik PT MSI',
        'status' => 'Aktif',
    ],
    [
        'name' => 'Finance CV Complete Selular',
        'code' => 'FINANCE_CVCS',
        'description' => 'Finance pencairan dan verifikasi nota balik CV Complete Selular',
        'status' => 'Aktif',
    ],
];

foreach ($rolesToAdd as $r) {
    $existing = App\Models\Role::where('code', $r['code'])->first();
    if (!$existing) {
        App\Models\Role::create($r);
        echo "Created role: {$r['name']} ({$r['code']})\n";
    } else {
        echo "Role exists: {$existing->name} ({$existing->code})\n";
    }
}

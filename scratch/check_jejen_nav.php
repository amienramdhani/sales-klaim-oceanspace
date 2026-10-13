<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$roles = [
    'Sales' => \App\Models\User::whereHas('employee.role', fn($q) => $q->where('code', 'SALES'))->first(),
    'ASM' => \App\Models\User::whereHas('employee.role', fn($q) => $q->where('code', 'ASM'))->first(),
    'RGM' => \App\Models\User::whereHas('employee.role', fn($q) => $q->where('code', 'RGM'))->first(),
    'Head of Sales' => \App\Models\User::where('name', 'like', '%Jejen%')->first(),
    'Admin' => \App\Models\User::where('email', 'like', '%admin%')->first(),
];

foreach ($roles as $roleName => $user) {
    if (!$user) continue;
    auth()->login($user);
    echo "\n=== ROLE: $roleName ({$user->name}) ===\n";
    $items = \Filament\Facades\Filament::getNavigation();
    foreach ($items as $group) {
        if (is_array($group)) {
            foreach ($group as $sub) {
                echo "Item: " . $sub->getLabel() . " => " . $sub->getUrl() . "\n";
            }
        } else {
            echo "Group: " . $group->getLabel() . "\n";
            foreach ($group->getItems() as $sub) {
                echo "  - " . $sub->getLabel() . " => " . $sub->getUrl() . "\n";
            }
        }
    }
}

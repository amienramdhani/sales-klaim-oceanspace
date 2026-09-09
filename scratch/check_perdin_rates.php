<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$emps = App\Models\Employee::with('role')->get()->map(function($e) {
    return [
        'id' => $e->id,
        'name' => $e->name,
        'position' => $e->position_name,
        'perdin_meal' => $e->perdin_meal_allowance,
        'perdin_lodging' => $e->perdin_lodging_allowance,
        'perdin_transport' => $e->perdin_transport_budget,
        'role_meal' => $e->role?->meal_allowance_per_day,
        'role_lodging' => $e->role?->lodging_allowance_per_night,
    ];
});

echo json_encode($emps, JSON_PRETTY_PRINT);

<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = collect(DB::select('DESCRIBE claims'))
    ->filter(fn($col) => in_array($col->Field, ['meal_allowance', 'lodging_allowance', 'toll_cost', 'fuel_cost', 'car_rental_cost', 'service_cost', 'days_count', 'nights_count', 'amount']))
    ->pluck('Null', 'Field')
    ->toArray();

echo json_encode($cols, JSON_PRETTY_PRINT);

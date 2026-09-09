<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Forms\Form;
use Filament\Forms\ComponentContainer;

echo "--- Testing EmployeeResource Form & Supervisor Relationship ---" . PHP_EOL;

$sampleEmp = Employee::first();
$livewire = new \App\Filament\Resources\EmployeeResource\Pages\EditEmployee();
$livewire->record = $sampleEmp;

$form = EmployeeResource::form(Form::make($livewire)->model($sampleEmp));
$livewire->form($form);

$closure = fn (\Illuminate\Database\Eloquent\Builder $query, ?Employee $record) => $record ? $query->where('id', '!=', $record->id) : $query;
$query = Employee::query();
$closure($query, $sampleEmp);
echo "Query SQL after closure: " . $query->toSql() . PHP_EOL;

echo "=== EmployeeResource Test PASSED! ===" . PHP_EOL;

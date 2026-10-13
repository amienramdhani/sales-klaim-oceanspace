<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 1. Delete unnecessary attendance docs and parts
Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
App\Models\AttendancePart::truncate();
App\Models\AttendanceDoc::truncate();
Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
echo "Attendance docs and parts truncated successfully.\n";

// 2. Remove attendance_image from items in claim 139 if any
$c = App\Models\Claim::find(139);
if ($c && is_array($c->items)) {
    $items = $c->items;
    foreach ($items as $idx => $it) {
        unset($items[$idx]['attendance_image']);
    }
    $c->items = $items;
    $c->save();
    echo "Claim 139 attendance_image cleaned up.\n";
}

// 3. Clean up attendance-generated folder in storage
$files = Illuminate\Support\Facades\Storage::disk('public')->files('attendance-generated');
foreach ($files as $f) {
    Illuminate\Support\Facades\Storage::disk('public')->delete($f);
    echo "Deleted generated file: {$f}\n";
}

echo "Cleanup finished successfully!\n";

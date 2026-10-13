<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

$toyo = User::where('custom_id', '2024.09.23.01')->first();
echo "Testing User: {$toyo->name}, NIK: {$toyo->custom_id}, Initial PIN: {$toyo->initial_pin}, MustChangePin: " . ($toyo->must_change_pin ? 'YES' : 'NO') . "\n";

// Test credentials array format like our Login page returns
$creds = [
    'id' => $toyo->id,
    'password' => $toyo->initial_pin,
];

$success = Auth::attempt($creds);
echo "Auth::attempt with NIK & PIN: " . ($success ? "SUCCESS! Logged in as: " . Auth::user()->name : "FAILED") . "\n";

// Test change PIN
if ($success) {
    $newPin = '654321';
    $toyo->update([
        'password' => Hash::make($newPin),
        'must_change_pin' => false,
        'pin_changed_at' => now(),
        'initial_pin' => null,
    ]);
    echo "PIN changed to {$newPin}. MustChangePin: " . ($toyo->fresh()->must_change_pin ? 'YES' : 'NO') . "\n";

    // Test login with new PIN
    $success2 = Auth::attempt(['id' => $toyo->id, 'password' => $newPin]);
    echo "Auth::attempt with new PIN: " . ($success2 ? "SUCCESS!" : "FAILED") . "\n";
}

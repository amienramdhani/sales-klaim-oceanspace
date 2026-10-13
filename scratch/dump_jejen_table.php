<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Set filament current panel
Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));

$user = App\Models\User::where('email', 'jejen@msi.com')->first();
auth()->login($user);

$livewire = Livewire\Livewire::test(App\Filament\Resources\JejenClaimApprovalResource\Pages\ListJejenClaimApprovals::class);
$html = $livewire->html();

file_put_contents('scratch/rendered_table.html', $html);

if (preg_match('/<table.*?<\/table>/s', $html, $matches)) {
    echo "TABLE FOUND, length: " . strlen($matches[0]) . "\n";
    if (preg_match('/<tbody.*?(<tr\b[^>]*>.*?<\/tr>)/s', $matches[0], $trMatches)) {
        echo "FIRST ROW:\n" . substr($trMatches[1], 0, 1500) . "\n";
    }
} else {
    echo "No <table> found, search for fi-ta classes\n";
}

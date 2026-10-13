<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

auth()->loginUsingId(1);
$request = Illuminate\Http\Request::create('/admin/bbm-claims', 'GET');
$response = app()->handle($request);
$content = $response->getContent();

echo "Length: " . strlen($content) . "\n";
echo "Has role=dialog: " . (str_contains($content, 'role="dialog"') ? 'YES' : 'NO') . "\n";
echo "Has fi-modal-window: " . (str_contains($content, 'fi-modal-window') ? 'YES' : 'NO') . "\n";
echo "Has fi-modal-open: " . (str_contains($content, 'fi-modal-open') ? 'YES' : 'NO') . "\n";

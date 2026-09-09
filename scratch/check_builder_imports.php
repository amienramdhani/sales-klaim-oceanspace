<?php

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/Filament/Resources'));
$found = 0;
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $c = file_get_contents($file->getPathname());
        if (preg_match('/\bBuilder\s+\$/', $c) && !str_contains($c, 'Illuminate\Database\Eloquent\Builder') && !str_contains($c, 'namespace App\Filament\Resources;')) {
            echo "Missing Builder import: " . $file->getPathname() . PHP_EOL;
            $found++;
        }
    }
}

if ($found === 0) {
    echo "All files have proper Builder imports!" . PHP_EOL;
}

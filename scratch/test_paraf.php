<?php
require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Str;

function generateDigitalParafBase64(string $name): string
{
    $width = 160;
    $height = 65;
    $img = imagecreatetruecolor($width, $height);

    imagealphablending($img, false);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
    imagefilledrectangle($img, 0, 0, $width, $height, $transparent);
    imagealphablending($img, true);

    $ink = imagecolorallocate($img, 15, 23, 42);
    $blueInk = imagecolorallocate($img, 2, 132, 199);
    $lineColor = imagecolorallocate($img, 148, 163, 184);

    $seed = crc32($name);
    mt_srand($seed);

    imagesetthickness($img, 2);
    $startX = 20;
    $startY = 32 + mt_rand(-4, 4);
    $points = [];
    $points[] = [$startX, $startY];
    
    $p1X = $startX + mt_rand(15, 22);
    $p1Y = $startY - mt_rand(12, 20);
    $points[] = [$p1X, $p1Y];

    $p2X = $p1X + mt_rand(15, 22);
    $p2Y = $startY + mt_rand(6, 12);
    $points[] = [$p2X, $p2Y];

    $p3X = $p2X + mt_rand(20, 30);
    $p3Y = $startY - mt_rand(8, 15);
    $points[] = [$p3X, $p3Y];

    $endX = min($p3X + mt_rand(25, 35), $width - 15);
    $endY = $startY + mt_rand(-3, 6);
    $points[] = [$endX, $endY];

    for ($i = 0; $i < count($points) - 1; $i++) {
        imageline($img, $points[$i][0], $points[$i][1], $points[$i+1][0], $points[$i+1][1], $ink);
    }

    imagearc($img, $startX + 10, $startY - 6, 20, 24, 0, 360, $ink);
    imageline($img, $startX - 5, $height - 18, $endX + 5, $height - 18, $lineColor);
    imagestring($img, 1, $startX, $height - 14, Str::limit(strtoupper($name), 16), $blueInk);

    ob_start();
    imagepng($img);
    $pngData = ob_get_clean();
    imagedestroy($img);

    return 'data:image/png;base64,' . base64_encode($pngData);
}

$b64 = generateDigitalParafBase64('Budi Santoso');
echo "Length of base64: " . strlen($b64) . "\n";
echo "Prefix: " . substr($b64, 0, 50) . "...\n";

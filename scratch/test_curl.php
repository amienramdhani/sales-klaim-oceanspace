<?php
$ch = curl_init('https://fonts.bunny.net/css?family=inter:400,500,600,700');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$start = microtime(true);
$res = curl_exec($ch);
$time = microtime(true) - $start;
echo 'Curl time: ' . round($time * 1000, 2) . ' ms | Error: ' . curl_error($ch) . "\n";

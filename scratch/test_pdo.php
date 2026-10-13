<?php
$t0 = microtime(true);
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=sales_klaim', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_PERSISTENT => false,
]);
$t1 = microtime(true);
echo "Direct PDO to 127.0.0.1: " . round(($t1 - $t0) * 1000, 2) . " ms\n";

$t2 = microtime(true);
$pdo2 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=sales_klaim', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_PERSISTENT => true,
]);
$t3 = microtime(true);
echo "Persistent PDO to 127.0.0.1: " . round(($t3 - $t2) * 1000, 2) . " ms\n";

<?php
$html = file_get_contents('scratch/rendered_table.html');
preg_match('/<tbody.*?(<tr\b[^>]*>.*?<\/tr>)/s', $html, $m);
preg_match_all('/<td\b([^>]*)>(.*?)<\/td>/s', $m[1], $tds, PREG_SET_ORDER);
foreach ($tds as $i => $td) {
    preg_match('/class="([^"]*)"/', $td[1], $c);
    echo "==================== TD #$i ====================\n";
    echo "CLASS: " . ($c[1] ?? '') . "\n";
    echo "CONTENT: " . trim($td[2]) . "\n\n";
}

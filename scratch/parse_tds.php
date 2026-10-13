<?php
$html = file_get_contents('scratch/rendered_table.html');
preg_match('/<tbody.*?(<tr\b[^>]*>.*?<\/tr>)/s', $html, $m);
preg_match_all('/<td\b([^>]*)>(.*?)<\/td>/s', $m[1], $tds, PREG_SET_ORDER);
foreach ($tds as $i => $td) {
    preg_match('/class="([^"]*)"/', $td[1], $c);
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($td[2])));
    echo "TD #$i:\n  CLASSES: " . ($c[1] ?? 'NONE') . "\n  TEXT: " . substr($text, 0, 100) . "\n\n";
}

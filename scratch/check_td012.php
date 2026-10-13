<?php
$html = file_get_contents('scratch/rendered_table.html');
preg_match('/<tbody.*?(<tr\b[^>]*>.*?<\/tr>)/s', $html, $m);
preg_match_all('/<td\b([^>]*)>(.*?)<\/td>/s', $m[1], $tds, PREG_SET_ORDER);
echo "TD 0:\n" . $tds[0][0] . "\n\n";
echo "TD 1:\n" . $tds[1][0] . "\n\n";
echo "TD 2:\n" . $tds[2][0] . "\n\n";

<?php
$html = file_get_contents('scratch/rendered_table.html');
preg_match('/<tbody.*?(<tr\b[^>]*>.*?<\/tr>)/s', $html, $m);
preg_match_all('/<td\b([^>]*)>(.*?)<\/td>/s', $m[1], $tds, PREG_SET_ORDER);
// Print TD 7 (actions cell)
echo $tds[7][0];

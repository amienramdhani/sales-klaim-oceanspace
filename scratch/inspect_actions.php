<?php
$html = file_get_contents('scratch/rendered_table.html');
preg_match('/<tbody.*?(<tr\b[^>]*>.*?<\/tr>)/s', $html, $m);
preg_match_all('/<td\b([^>]*)>(.*?)<\/td>/s', $m[1], $tds, PREG_SET_ORDER);
preg_match_all('/<button\b([^>]*)>(.*?)<\/button>/s', $tds[7][0], $btns, PREG_SET_ORDER);
foreach ($btns as $i => $b) {
    preg_match('/class="([^"]*)"/', $b[1], $c);
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($b[2])));
    echo "Button #$i: text='$text' class='" . ($c[1] ?? '') . "'\n";
}

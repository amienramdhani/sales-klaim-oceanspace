<?php
$html = file_get_contents('scratch/rendered_table.html');
// Check all classes on wrapper elements around table
preg_match('/class="([^"]*fi-ta[^"]*)"/s', $html, $m);
preg_match_all('/<div\b([^>]*)>/s', substr($html, 0, 2000), $divs);
foreach ($divs[1] as $d) {
    if (preg_match('/class="([^"]*)"/', $d, $c)) {
        echo "DIV: " . $c[1] . "\n";
    }
}

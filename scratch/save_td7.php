<?php
$html = file_get_contents('scratch/rendered_table.html');
preg_match('/<td[^>]*fi-ta-actions-cell[^>]*>(.*?)<\/td>/s', $html, $m);
file_put_contents('scratch/td7.html', $m[0]);
echo "Saved td7.html (" . strlen($m[0]) . " bytes)\n";

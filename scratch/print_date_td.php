<?php
$html = file_get_contents('scratch/rendered_table.html');
preg_match('/<td[^>]*fi-table-cell-claim-date[^>]*>.*?<\/td>/s', $html, $m);
echo $m[0];

<?php
$html = file_get_contents('scratch/rendered_table.html');
preg_match('/<td[^>]*fi-ta-actions-cell[^>]*>(.*?)<\/td>/s', $html, $m);
// Print direct children of fi-ta-actions
if (preg_match('/<div[^>]*class="[^"]*fi-ta-actions[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/s', $m[1], $actionsDiv)) {
    echo "INSIDE fi-ta-actions:\n";
    // Find all direct children or tags
    echo substr($actionsDiv[1], 0, 1000) . "\n";
} else {
    echo "fi-ta-actions not matched\n";
}

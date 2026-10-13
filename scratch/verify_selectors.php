<?php
// Verify our selectors against the HTML
$html = file_get_contents('scratch/rendered_table.html');

$checks = [
    'selection-cell' => '/<td[^>]*fi-ta-selection-cell/i',
    'category-cell' => '/<td[^>]*fi-table-cell-claim-category/i',
    'date-cell' => '/<td[^>]*fi-table-cell-claim-date/i',
    'employee-cell' => '/<td[^>]*effective-employee/i',
    'amount-cell' => '/<td[^>]*fi-table-cell-amount/i',
    'status-cell' => '/<td[^>]*fi-table-cell-approval-status/i',
    'actions-cell' => '/<td[^>]*fi-ta-actions-cell/i',
    'link-action (Berkas)' => '/<button[^>]*fi-ac-link-action/i',
    'btn-action (ACC/Tolak)' => '/<button[^>]*fi-ac-btn-action/i',
    'icon-btn (Download)' => '/<button[^>]*fi-ac-icon-btn-group/i',
];

foreach ($checks as $name => $pattern) {
    $match = preg_match($pattern, $html);
    echo ($match ? "[OK] " : "[FAIL] ") . $name . "\n";
}

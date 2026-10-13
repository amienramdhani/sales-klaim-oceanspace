<?php
$rendered = file_get_contents('scratch/rendered_table.html');
if (preg_match('/<table\b.*?<\/table>/s', $rendered, $m)) {
    echo "Found table, length: " . strlen($m[0]) . "\n";
    $tableHtml = '<div class="fi-ta"><div class="fi-ta-content">' . $m[0] . '</div></div>';
} else {
    echo "Table NOT found\n";
    $tableHtml = $rendered;
}

$appCss = file_get_contents('public/css/filament/filament/app.css');
$formsCss = file_get_contents('public/css/filament/forms/forms.css');
$supportCss = file_get_contents('public/css/filament/support/support.css');
$mobileCss = file_get_contents('public/css/filament-mobile.css');

$html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Preview Mobile Approval</title>
    <style>
        :root {
            --primary-50: #ecfdf5;
            --primary-400: #34d399;
            --primary-500: #10b981;
            --primary-600: #059669;
            --success-50: #f0fdf4;
            --success-400: #4ade80;
            --success-500: #22c55e;
            --success-600: #16a34a;
            --danger-50: #fef2f2;
            --danger-400: #f87171;
            --danger-500: #ef4444;
            --danger-600: #dc2626;
            --warning-50: #fffbeb;
            --warning-400: #fbbf24;
            --warning-500: #f59e0b;
            --warning-600: #d97706;
            --info-50: #f0f9ff;
            --info-400: #38bdf8;
            --info-500: #0ea5e9;
            --info-600: #0284c7;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-950: #020617;
        }

        $appCss
        $formsCss
        $supportCss
        $mobileCss

        * {
            box-sizing: border-box;
        }

        body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 8px;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            overflow-x: hidden;
            width: 100%;
        }

        .phone-frame {
            max-width: 360px;
            margin: 0 auto;
        }

        .header-section {
            padding: 4px 4px 8px 4px;
        }

        .header-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
        }
        .header-subtitle {
            font-size: 0.75rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="phone-frame">
        <div class="header-section">
            <div class="header-title">Persetujuan Klaim</div>
            <div class="header-subtitle">Role: Head of Sales (Pak Jejen)</div>
        </div>
        $tableHtml
    </div>
</body>
</html>
HTML;

file_put_contents('public/test-mobile-preview.html', $html);
echo "Generated public/test-mobile-preview.html (" . strlen($html) . " bytes)\n";

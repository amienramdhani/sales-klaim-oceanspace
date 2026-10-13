<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$appCss = file_get_contents('public/css/filament/filament/app.css');
$formsCss = file_get_contents('public/css/filament/forms/forms.css');
$supportCss = file_get_contents('public/css/filament/support/support.css');
$mobileCss = file_get_contents('public/css/filament-mobile.css');

$html = <<<HTML
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dark Mode Verification</title>
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
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --gray-950: #020617;
        }

        $appCss
        $formsCss
        $supportCss
        $mobileCss

        body {
            background-color: #0f172a;
            color: #f8fafc;
            padding: 16px;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .container-box {
            max-width: 480px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
    </style>
</head>
<body class="dark bg-gray-900 text-white">
    <div class="container-box">
        <h2 class="text-xl font-bold text-white mb-2">Dark Mode Inspection</h2>

        <!-- 1. Dropdown Panel and Items -->
        <div class="p-4 bg-gray-800 rounded-xl border border-gray-700">
            <h3 class="text-sm font-semibold text-gray-300 mb-3">1. Dropdown Actions (Aksi Menu)</h3>
            <div class="fi-dropdown-panel" style="display: block; position: relative;">
                <div class="fi-dropdown-list">
                    <a class="fi-dropdown-list-item fi-color-success" href="#">
                        <svg class="fi-dropdown-list-item-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="fi-dropdown-list-item-label">Unduh Excel (.xlsx)</span>
                    </a>
                    <a class="fi-dropdown-list-item fi-color-danger" href="#">
                        <svg class="fi-dropdown-list-item-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="fi-dropdown-list-item-label">Unduh PDF Form</span>
                    </a>
                    <a class="fi-dropdown-list-item fi-color-info" href="#">
                        <svg class="fi-dropdown-list-item-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="fi-dropdown-list-item-label">Word (.docx)</span>
                    </a>
                    <a class="fi-dropdown-list-item fi-color-warning" href="#">
                        <svg class="fi-dropdown-list-item-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="fi-dropdown-list-item-label">Ubah / Edit</span>
                    </a>
                    <!-- Default Gray Item (Lihat / Hapus) -->
                    <button type="button" class="fi-dropdown-list-item fi-color-gray">
                        <svg class="fi-dropdown-list-item-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="fi-dropdown-list-item-label">Lihat Rincian</span>
                    </button>
                    <button type="button" class="fi-dropdown-list-item">
                        <svg class="fi-dropdown-list-item-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span class="fi-dropdown-list-item-label">Hapus Pengajuan</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. Table Action Buttons -->
        <div class="p-4 bg-gray-800 rounded-xl border border-gray-700">
            <h3 class="text-sm font-semibold text-gray-300 mb-3">2. Table Action Trigger & Buttons</h3>
            <div class="fi-ta-actions-cell">
                <div class="fi-ta-actions">
                    <a class="fi-ac-link-action fi-link" href="#">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        <span>Berkas (3)</span>
                    </a>
                    <div class="fi-dropdown-trigger">
                        <button type="button" aria-haspopup="menu">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                        </button>
                    </div>
                    <button class="fi-btn fi-btn-color-success" type="button">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="fi-btn-label">ACC Klaim</span>
                    </button>
                    <button class="fi-btn fi-btn-color-danger" type="button">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span class="fi-btn-label">Tolak</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. Attendance Doc Table Row Card -->
        <div class="p-4 bg-gray-800 rounded-xl border border-gray-700">
            <h3 class="text-sm font-semibold text-gray-300 mb-3">3. Attendance Doc Row Card</h3>
            <div class="fi-ta">
                <div class="fi-ta-content">
                    <table class="fi-ta-table">
                        <tbody>
                            <tr class="fi-ta-row">
                                <td class="fi-ta-selection-cell">
                                    <input type="checkbox" class="fi-checkbox-input" />
                                </td>
                                <td class="fi-table-cell-category">
                                    <span class="fi-badge bg-emerald-950/40 text-emerald-400 border border-emerald-800 px-2 py-0.5 rounded-full text-xs font-semibold">Makan</span>
                                </td>
                                <td class="fi-table-cell-claim-date">
                                    <span class="fi-ta-text-item">23 Sep 2026</span>
                                </td>
                                <td class="fi-table-cell-effective-employee">
                                    <span class="fi-ta-text-item">ARIS MUNANDAR</span>
                                    <span class="fi-ta-text-item-description">AREA SALES MANAGER (ASM)</span>
                                </td>
                                <td class="fi-table-cell-purpose">
                                    <div class="text-xs text-gray-300 font-medium mt-1">Perihal: Koordinasi Bulanan Tim Sales Cirebon</div>
                                </td>
                                <td class="fi-table-cell-amount">
                                    <span class="fi-ta-text-item">Rp 350.000</span>
                                </td>
                                <td class="fi-table-cell-approval-status">
                                    <span class="fi-badge bg-amber-950/40 text-amber-400 border border-amber-800 px-2 py-0.5 rounded-full text-xs font-semibold">Menunggu Penyesuaian</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 4. Notice Banner -->
        <div class="p-4 bg-gray-800 rounded-xl border border-gray-700">
            <h3 class="text-sm font-semibold text-gray-300 mb-3">4. Notice Banner Entertain</h3>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-900 dark:text-emerald-100 text-sm">
                <div class="flex items-start gap-2.5">
                    <span class="text-2xl">📋</span>
                    <div>
                        <p class="font-bold text-emerald-800 dark:text-emerald-200 text-sm">Khusus Pengajuan Biaya Entertain (Makan Tim & Ulang Tahun):</p>
                        <p class="text-xs text-emerald-700 dark:text-emerald-300 mt-0.5">Role ASM dan RGM cukup mengisi <strong>Dokumen Daftar Hadir</strong>. Data agenda, peserta, dan foto nota yang Anda upload akan langsung masuk ke Admin untuk disesuaikan dan diproses menjadi klaim resmi.</p>
                    </div>
                </div>
                <a href="#" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white rounded-lg text-xs font-bold shadow transition shrink-0">
                    <span>📝 Isi Dokumen Daftar Hadir</span>
                </a>
            </div>
        </div>

        <!-- 5. Status Section with #6dbbefff -->
        <div class="p-4 bg-gray-800 rounded-xl border border-gray-700">
            <h3 class="text-sm font-semibold text-gray-300 mb-3">5. Status Section (_UID & Status Pengajuan)</h3>
            <div style="background: #6dbbefff; border-radius: 12px; padding: 1.2rem;">
                <div class="text-sm font-bold">_UID EXTERNAL & STATUS PENGAJUAN</div>
                <p class="text-xs text-gray-700 dark:text-gray-300">Nomor referensi ID Form External dan status approval sistem.</p>
                <div class="mt-2">
                    <label class="block text-xs font-medium text-gray-950 dark:text-white">_UID (ID Form External)</label>
                    <input type="text" value="UID-TE-2026-001" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-950 dark:text-white px-3 py-1.5 text-sm" />
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

file_put_contents('public/test-dark-mode.html', $html);
echo "Wrote public/test-dark-mode.html (" . strlen($html) . " bytes)\n";

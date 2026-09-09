<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\ClaimPeriod;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimExcelExportService
{
    /**
     * Export a single claim record (with all its item rows) for one person
     */
    public function exportSingleClaim(Claim $claim): StreamedResponse
    {
        $emp = $claim->effective_employee;
        $empName = $emp?->name ?? 'Karyawan';
        $empPosition = $emp?->position_name ?? 'RGM';
        $claimDate = $claim->claim_date ? $claim->claim_date->translatedFormat('d F Y') : date('d F Y');
        $monthYear = strtoupper($claim->claim_date ? $claim->claim_date->translatedFormat('F Y') : date('F Y'));
        $isPerdin = ($claim->is_perdin || $claim->claim_category === 'perdin');
        $titleType = $isPerdin ? 'PERJALANAN DINAS' : 'OPERASIONAL';
        $title = "REALISASI BIAYA {$titleType} {$empPosition} — BULAN {$monthYear}";

        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName);
        $prefix = $isPerdin ? 'Perdin' : 'Klaim';
        $filename = "{$prefix}_{$cleanName}_" . date('Ymd_His') . ".xlsx";

        return $this->generateSpreadsheetFromSingleClaim($claim, $title, $empName, $empPosition, $claimDate, $filename);
    }

    /**
     * Export multiple claims
     */
    public function exportClaims(Collection $claims, string $title = 'REALISASI BIAYA OPERASIONAL'): StreamedResponse
    {
        $firstClaim = $claims->first();
        $emp = $firstClaim?->effective_employee;
        $empName = $emp?->name ?? 'Karyawan';
        $empPosition = $emp?->position_name ?? 'RGM';
        $claimDate = $firstClaim?->claim_date ? $firstClaim->claim_date->translatedFormat('d F Y') : date('d F Y');
        $filename = "Rekap_Klaim_" . date('Ymd_His') . ".xlsx";

        return $this->generateSpreadsheetFromClaimsCollection($claims, $title, $empName, $empPosition, $claimDate, $filename);
    }

    /**
     * Generate Excel sheet for a single claim submission
     * Layout: Budget Claim, Sudah Claim, Biaya Claim, Over Budget letakkan tepat di bawah Tanggal Pengajuan
     */
    protected function generateSpreadsheetFromSingleClaim(Claim $claim, string $title, string $empName, string $empPosition, string $claimDate, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Realisasi Klaim');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        // 1. Kolom A Dikosongkan untuk margin
        $sheet->getColumnDimension('A')->setWidth(4);

        // 2. Title Header (Merged B1:I1)
        $sheet->mergeCells('B1:I1');
        $sheet->setCellValue('B1', $title);
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 3. Metadata Section
        $sheet->setCellValue('B3', 'Nama / Jabatan');
        $sheet->setCellValue('C3', ": {$empName} / {$empPosition}");
        $sheet->getStyle('B3')->getFont()->setBold(true);

        $sheet->setCellValue('B4', 'Tanggal Pengajuan');
        $sheet->setCellValue('C4', ": {$claimDate}");
        $sheet->getStyle('B4')->getFont()->setBold(true);

        // Budget Claim, Sudah Claim, Biaya Claim, Over Budget DIBAWAH TANGGAL
        $emp = $claim->effective_employee;
        $budgetVal = $emp ? $emp->total_budget : 0;
        $alreadyVal = $claim->claimPeriod ? (float)$claim->claimPeriod->already_claimed : 0;
        $biayaVal = (float)$claim->amount;
        $overBudgetVal = ($biayaVal + $alreadyVal > $budgetVal && $budgetVal > 0) ? ($biayaVal + $alreadyVal - $budgetVal) : 0;

        $sheet->setCellValue('B5', 'Budget Claim');
        $sheet->setCellValue('C5', ": -");
        $sheet->getStyle('B5')->getFont()->setBold(true);

        $sheet->setCellValue('B6', 'Sudah Claim');
        if ($alreadyVal > 0) {
            $sheet->setCellValue('C6', ": Rp " . number_format($alreadyVal, 0, ',', '.'));
        } else {
            $sheet->setCellValue('C6', ": -");
        }
        $sheet->getStyle('B6')->getFont()->setBold(true);

        $sheet->setCellValue('B7', 'Biaya Claim');
        $sheet->setCellValue('C7', ": Rp " . number_format($biayaVal, 0, ',', '.'));
        $sheet->getStyle('B7')->getFont()->setBold(true);
        $sheet->getStyle('C7')->getFont()->setBold(true)->getColor()->setRGB('059669');

        $sheet->setCellValue('B8', 'Over Budget');
        if ($overBudgetVal > 0) {
            $sheet->setCellValue('C8', ": Rp " . number_format($overBudgetVal, 0, ',', '.'));
            $sheet->getStyle('C8')->getFont()->setBold(true)->getColor()->setRGB('DC2626');
        } else {
            $sheet->setCellValue('C8', ": -");
        }
        $sheet->getStyle('B8')->getFont()->setBold(true);

        $sheet->setCellValue('B9', 'Nomor Pengajuan');
        $sheet->setCellValue('C9', ": " . ($claim->claimPeriod?->period_number ?? '-'));
        $sheet->getStyle('B9')->getFont()->setBold(true);

        // Status Pencairan
        $sheet->setCellValue('B10', 'Status Pencairan');
        $sheet->setCellValue('C10', ": " . ($claim->disbursement_status ?? 'Belum Dicairkan'));
        $sheet->getStyle('B10')->getFont()->setBold(true);

        // 4. Table Header (Row 12)
        // 4. Table Header (Row 12)
        $isBbmClaim = ($claim->claim_category === 'bbm' || in_array('BBM', (array)$claim->claim_type) || str_contains(strtoupper($claim->claim_type_string ?? ''), 'BBM'));
        $headerRow = 12;

        if ($isBbmClaim) {
            $headers = [
                'B' => 'TANGGAL NOTA',
                'C' => 'JENIS BIAYA',
                'D' => 'KEPERLUAN',
                'E' => 'NOTE / LOKASI',
                'F' => 'KM AWAL',
                'G' => 'NOMINAL',
                'H' => 'BRAND',
                'I' => 'REFFNOTE',
                'J' => 'KOTA',
            ];
            $lastCol = 'J';
        } else {
            $headers = [
                'B' => 'TANGGAL NOTA',
                'C' => 'JENIS BIAYA',
                'D' => 'KEPERLUAN',
                'E' => 'NOTE / LOKASI',
                'F' => 'NOMINAL',
                'G' => 'BRAND',
                'H' => 'REFFNOTE',
                'I' => 'KOTA',
            ];
            $lastCol = 'I';
        }

        foreach ($headers as $col => $headerText) {
            $sheet->setCellValue("{$col}{$headerRow}", $headerText);
        }

        $headerRange = "B{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(25);

        // 5. Data Rows
        $currentRow = $headerRow + 1;
        $lineItems = $claim->getLineItems();
        $formattedDate = $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-';

        foreach ($lineItems as $item) {
            $itemDate = !empty($item['receipt_date']) ? date('d/m/Y', strtotime($item['receipt_date'])) : $formattedDate;
            $itemType = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? 'Entertain');
            $itemPurpose = is_array($item['purpose'] ?? null) ? implode(', ', $item['purpose']) : ($item['purpose'] ?? '-');
            $itemNote = $item['note'] ?? '-';
            $itemKm = !empty($item['fuel_start_km']) ? number_format((float)$item['fuel_start_km'], 0, ',', '.') . ' KM' : ($claim->fuel_start_km ? number_format((float)$claim->fuel_start_km, 0, ',', '.') . ' KM' : '-');
            $itemCity = $item['city'] ?? ($claim->city ?? '-');
            $itemBrand = $item['brand'] ?? ($claim->brand ?? ($claim->branch?->brand ?? '-'));
            $itemReffnote = $item['reffnote'] ?? ($claim->reffnote ?? ($claim->branch?->reffnote ?? '-'));
            $itemAmount = (float)($item['amount'] ?? 0);

            $sheet->setCellValue("B{$currentRow}", $itemDate);
            $sheet->setCellValue("C{$currentRow}", $itemType);
            $sheet->setCellValue("D{$currentRow}", $itemPurpose);
            $sheet->setCellValue("E{$currentRow}", $itemNote);

            if ($isBbmClaim) {
                $sheet->setCellValue("F{$currentRow}", $itemKm);
                $sheet->setCellValue("G{$currentRow}", $itemAmount);
                $sheet->setCellValue("H{$currentRow}", $itemBrand);
                $sheet->setCellValue("I{$currentRow}", $itemReffnote);
                $sheet->setCellValue("J{$currentRow}", $itemCity);

                $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("J{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } else {
                $sheet->setCellValue("F{$currentRow}", $itemAmount);
                $sheet->setCellValue("G{$currentRow}", $itemBrand);
                $sheet->setCellValue("H{$currentRow}", $itemReffnote);
                $sheet->setCellValue("I{$currentRow}", $itemCity);

                $sheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($currentRow % 2 === 0) {
                $sheet->getStyle("B{$currentRow}:{$lastCol}{$currentRow}")->getFill()->applyFromArray([
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFC'],
                ]);
            }

            $currentRow++;
        }

        // 6. Total Row
        if ($isBbmClaim) {
            $sheet->mergeCells("B{$currentRow}:F{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", 'TOTAL BIAYA CLAIM');
            $sheet->setCellValue("G{$currentRow}", "=SUM(G{$headerRow}:G" . ($currentRow - 1) . ")");
            $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        } else {
            $sheet->mergeCells("B{$currentRow}:E{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", 'TOTAL BIAYA CLAIM');
            $sheet->setCellValue("F{$currentRow}", "=SUM(F{$headerRow}:F" . ($currentRow - 1) . ")");
            $sheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        }

        $totalRange = "B{$currentRow}:{$lastCol}{$currentRow}";
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        $tableEndRow = $currentRow;

        // Apply borders for main table
        $tableRange = "B{$headerRow}:{$lastCol}{$tableEndRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        $currentRow++;

        // 6b. Additional Summary for BBM Claim / Budget (Budget Awal & Pengembalian Dana / Over Budget)
        $isBbmClaim = ($claim->claim_category === 'bbm' || in_array('BBM', (array)$claim->claim_type));
        $bbmBudget = (float)($emp?->bbm_budget ?? ($emp?->positionModel?->bbm_budget ?? 0));

        if ($isBbmClaim && $bbmBudget > 0) {
            $totalDisetujui = (float)$claim->amount;
            $sisaDiff = $bbmBudget - $totalDisetujui;
            $isOverBudget = ($sisaDiff < 0);

            // Row Budget Awal
            $sheet->mergeCells("B{$currentRow}:F{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", 'BUDGET AWAL BBM');
            $sheet->setCellValue("G{$currentRow}", $bbmBudget);
            $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("B{$currentRow}:J{$currentRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;

            // Row Pengembalian / Over Budget
            $sheet->mergeCells("B{$currentRow}:F{$currentRow}");
            $rowTitle = $isOverBudget ? 'OVER BUDGET' : 'PENGEMBALIAN DANA';
            $sheet->setCellValue("B{$currentRow}", $rowTitle);
            $sheet->setCellValue("G{$currentRow}", abs($sisaDiff));
            $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("B{$currentRow}:J{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $isOverBudget ? 'DC2626' : '0F766E']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $isOverBudget ? 'FEE2E2' : 'E2E8F0']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getRowDimension($currentRow)->setRowHeight(22);
            $currentRow++;

            // Row Catatan Pengembalian
            $catatanText = '';
            if ($sisaDiff > 0) {
                $catatanText = "Catatan Pengembalian: Karyawan telah menerima budget BBM awal Rp " . number_format($bbmBudget, 0, ',', '.') . ". Realisasi klaim Rp " . number_format($totalDisetujui, 0, ',', '.') . ". Sisa dana yang WAJIB DIKEMBALIKAN ke finance adalah sebesar Rp " . number_format($sisaDiff, 0, ',', '.') . ".";
            } elseif ($sisaDiff < 0) {
                $catatanText = "Catatan Over Budget: Realisasi klaim BBM Rp " . number_format($totalDisetujui, 0, ',', '.') . " melebihi budget awal Rp " . number_format($bbmBudget, 0, ',', '.') . " (Over Budget: Rp " . number_format(abs($sisaDiff), 0, ',', '.') . ").";
            } else {
                $catatanText = "Catatan Pengembalian: Realisasi klaim BBM tepat sesuai budget awal yang diberikan (Nihil pengembalian).";
            }

            $sheet->mergeCells("B{$currentRow}:J{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", $catatanText);
            $sheet->getStyle("B{$currentRow}")->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($isOverBudget ? 'DC2626' : '475569'));
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($currentRow)->setRowHeight(28);
            $currentRow++;
        }

        foreach (range('B', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 7. Embed All Photos (BBM Before & After pairs, Struk/Nota, Return Transfer Proof, Finance Proof)
        $this->embedClaimPhotosToWorksheet($sheet, $claim, $currentRow + 2, true);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Generate Excel sheet for multiple claims
     */
    protected function generateSpreadsheetFromClaimsCollection(Collection $claims, string $title, string $empName, string $empPosition, string $claimDate, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Realisasi Klaim');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(4);

        // Title Header
        $sheet->mergeCells('B1:I1');
        $sheet->setCellValue('B1', $title);
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $firstClaim = $claims->first();
        $emp = $firstClaim?->effective_employee;
        $totalAmount = $claims->sum('amount');
        $budgetVal = $emp ? $emp->total_budget : 0;
        $alreadyVal = $firstClaim?->claimPeriod ? (float)$firstClaim->claimPeriod->already_claimed : 0;
        $overBudgetVal = ($totalAmount + $alreadyVal > $budgetVal && $budgetVal > 0) ? ($totalAmount + $alreadyVal - $budgetVal) : 0;

        // Metadata tepat di bawah tanggal
        $sheet->setCellValue('B3', 'Nama / Jabatan');
        $sheet->setCellValue('C3', ": {$empName} / {$empPosition}");
        $sheet->getStyle('B3')->getFont()->setBold(true);

        $sheet->setCellValue('B4', 'Tanggal Pengajuan');
        $sheet->setCellValue('C4', ": {$claimDate}");
        $sheet->getStyle('B4')->getFont()->setBold(true);

        $sheet->setCellValue('B5', 'Budget Claim');
        $sheet->setCellValue('C5', ": -");
        $sheet->getStyle('B5')->getFont()->setBold(true);

        $sheet->setCellValue('B6', 'Sudah Claim');
        if ($alreadyVal > 0) {
            $sheet->setCellValue('C6', ": Rp " . number_format($alreadyVal, 0, ',', '.'));
        } else {
            $sheet->setCellValue('C6', ": -");
        }
        $sheet->getStyle('B6')->getFont()->setBold(true);

        $sheet->setCellValue('B7', 'Biaya Claim');
        $sheet->setCellValue('C7', ": Rp " . number_format($totalAmount, 0, ',', '.'));
        $sheet->getStyle('B7')->getFont()->setBold(true);
        $sheet->getStyle('C7')->getFont()->setBold(true)->getColor()->setRGB('059669');

        $sheet->setCellValue('B8', 'Over Budget');
        if ($overBudgetVal > 0) {
            $sheet->setCellValue('C8', ": Rp " . number_format($overBudgetVal, 0, ',', '.'));
            $sheet->getStyle('C8')->getFont()->setBold(true)->getColor()->setRGB('DC2626');
        } else {
            $sheet->setCellValue('C8', ": -");
        }
        $sheet->getStyle('B8')->getFont()->setBold(true);

        $sheet->setCellValue('B9', 'Nomor Pengajuan');
        $sheet->setCellValue('C9', ": " . ($firstClaim?->claimPeriod?->period_number ?? '-'));
        $sheet->getStyle('B9')->getFont()->setBold(true);

        // Table Header
        $hasBbm = $claims->contains(function ($c) {
            return $c->claim_category === 'bbm'
                || in_array('BBM', (array)$c->claim_type)
                || str_contains(strtoupper($c->claim_type_string ?? ''), 'BBM');
        });

        $headerRow = 11;
        if ($hasBbm) {
            $headers = [
                'B' => 'TANGGAL NOTA',
                'C' => 'JENIS BIAYA',
                'D' => 'KEPERLUAN',
                'E' => 'NOTE / LOKASI',
                'F' => 'KM AWAL',
                'G' => 'NOMINAL',
                'H' => 'BRAND',
                'I' => 'REFFNOTE',
                'J' => 'KOTA',
            ];
            $lastCol = 'J';
        } else {
            $headers = [
                'B' => 'TANGGAL NOTA',
                'C' => 'JENIS BIAYA',
                'D' => 'KEPERLUAN',
                'E' => 'NOTE / LOKASI',
                'F' => 'NOMINAL',
                'G' => 'BRAND',
                'H' => 'REFFNOTE',
                'I' => 'KOTA',
            ];
            $lastCol = 'I';
        }

        foreach ($headers as $col => $headerText) {
            $sheet->setCellValue("{$col}{$headerRow}", $headerText);
        }

        $headerRange = "B{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(25);

        // Data Rows
        $currentRow = $headerRow + 1;

        foreach ($claims as $claim) {
            $formattedDate = $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-';

            foreach ($claim->getLineItems() as $item) {
                $itemDate = !empty($item['receipt_date']) ? date('d/m/Y', strtotime($item['receipt_date'])) : $formattedDate;
                $itemType = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? 'Entertain');
                $itemPurpose = is_array($item['purpose'] ?? null) ? implode(', ', $item['purpose']) : ($item['purpose'] ?? '-');
                $itemNote = $item['note'] ?? '-';
                $itemKm = !empty($item['fuel_start_km']) ? number_format((float)$item['fuel_start_km'], 0, ',', '.') . ' KM' : ($claim->fuel_start_km ? number_format((float)$claim->fuel_start_km, 0, ',', '.') . ' KM' : '-');
                $itemCity = $item['city'] ?? ($claim->city ?? '-');
                $itemBrand = $item['brand'] ?? ($claim->brand ?? ($claim->branch?->brand ?? '-'));
                $itemReffnote = $item['reffnote'] ?? ($claim->reffnote ?? ($claim->branch?->reffnote ?? '-'));
                $itemAmount = (float)($item['amount'] ?? 0);

                $sheet->setCellValue("B{$currentRow}", $itemDate);
                $sheet->setCellValue("C{$currentRow}", $itemType);
                $sheet->setCellValue("D{$currentRow}", $itemPurpose);
                $sheet->setCellValue("E{$currentRow}", $itemNote);

                if ($hasBbm) {
                    $sheet->setCellValue("F{$currentRow}", $itemKm);
                    $sheet->setCellValue("G{$currentRow}", $itemAmount);
                    $sheet->setCellValue("H{$currentRow}", $itemBrand);
                    $sheet->setCellValue("I{$currentRow}", $itemReffnote);
                    $sheet->setCellValue("J{$currentRow}", $itemCity);

                    $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                    $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("J{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $sheet->setCellValue("F{$currentRow}", $itemAmount);
                    $sheet->setCellValue("G{$currentRow}", $itemBrand);
                    $sheet->setCellValue("H{$currentRow}", $itemReffnote);
                    $sheet->setCellValue("I{$currentRow}", $itemCity);

                    $sheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                    $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($currentRow % 2 === 0) {
                    $sheet->getStyle("B{$currentRow}:{$lastCol}{$currentRow}")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ]);
                }

                $currentRow++;
            }
        }

        // Total Row
        if ($hasBbm) {
            $sheet->mergeCells("B{$currentRow}:F{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", 'TOTAL BIAYA CLAIM');
            $sheet->setCellValue("G{$currentRow}", "=SUM(G{$headerRow}:G" . ($currentRow - 1) . ")");
            $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        } else {
            $sheet->mergeCells("B{$currentRow}:E{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", 'TOTAL BIAYA CLAIM');
            $sheet->setCellValue("F{$currentRow}", "=SUM(F{$headerRow}:F" . ($currentRow - 1) . ")");
            $sheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        }

        $totalRange = "B{$currentRow}:{$lastCol}{$currentRow}";
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        $tableRange = "B{$headerRow}:{$lastCol}{$currentRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        foreach (range('B', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Check if any claims have photos (BBM, general receipts, return transfer proof, finance proof)
        $hasPhotos = false;
        foreach ($claims as $c) {
            if (!empty($c->getBbmPairCombinedPhotos()) || !empty($c->photos) || !empty($c->return_transfer_proof) || !empty($c->transfer_proof_photo) || !empty($c->items)) {
                $hasPhotos = true;
                break;
            }
        }

        if ($hasPhotos) {
            $photoSheet = $spreadsheet->createSheet();
            $photoSheet->setTitle('Lampiran Foto Klaim');
            $photoSheet->getColumnDimension('A')->setWidth(4);
            $photoSheet->mergeCells('B1:I1');
            $photoSheet->setCellValue('B1', 'LAMPIRAN FOTO DOKUMENTASI & BUKTI PENGEMBALIAN DANA');
            $photoSheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
            $photoSheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $pRow = 3;
            foreach ($claims as $c) {
                $pEmp = $c->effective_employee;
                $pEmpName = $pEmp?->name ?? 'Karyawan';
                $pEmpRole = $pEmp?->position_name ?? 'ASM';
                $pDate = $c->claim_date ? $c->claim_date->format('d/m/Y') : '-';
                $pUid = $c->_uid ?: '-';
                $pTotal = 'Rp ' . number_format((float)$c->amount, 0, ',', '.');

                $claimHeader = "KLAIM: {$pEmpName} ({$pEmpRole}) | TGL: {$pDate} | _UID: {$pUid} | TOTAL: {$pTotal}";
                $photoSheet->mergeCells("B{$pRow}:I{$pRow}");
                $photoSheet->setCellValue("B{$pRow}", $claimHeader);
                $photoSheet->getStyle("B{$pRow}:I{$pRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F766E'],
                    ],
                ]);
                $pRow += 2;

                $pRow = $this->embedClaimPhotosToWorksheet($photoSheet, $c, $pRow, false);
                $pRow += 2;
            }

            $sheet->setCellValue("B" . ($currentRow + 2), '* Seluruh foto dokumentasi & bukti transfer terlampir lengkap di Sheet "Lampiran Foto Klaim".');
            $sheet->getStyle("B" . ($currentRow + 2))->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F766E'));
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Form Pengajuan Perjalanan Dinas Sesuai Format Resmi Perusahaan (PT. MEDIA SELULAR INDONESIA)
     */
    public function exportPerdinForm(?Claim $claim = null, array $customData = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Form Perdin');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        // Column Margins & Widths
        $sheet->getColumnDimension('A')->setWidth(3);
        $sheet->getColumnDimension('B')->setWidth(26);
        $sheet->getColumnDimension('C')->setWidth(4);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(22);

        $emp = $claim?->effective_employee;
        $empName = $customData['name'] ?? ($emp?->name ?? 'RIVENJER BILLY KAPAHANG');
        $empPosition = $customData['position'] ?? ($emp?->position_name ?? 'ASM');
        $destination = $customData['destination'] ?? ($claim?->destination_city ?? 'KEPULAUAN TAHUNA');
        $purpose = $customData['purpose'] ?? ($claim?->purpose_string ?? 'VISIT DEALER');
        $claimDate = $claim?->claim_date ? $claim->claim_date->translatedFormat('d F Y') : date('d F Y');
        $tglBerangkat = $claim?->claim_date ? $claim->claim_date->format('d-M-y') : date('d-M-y');
        $days = $claim?->days_count ?? ($customData['days'] ?? 3);
        $tglPulang = $claim?->claim_date ? $claim->claim_date->copy()->addDays($days)->format('d-M-y') : date('d-M-y', strtotime("+{$days} days"));

        $mealAllowance = (float)($claim?->meal_allowance ?? 300000);
        $mealDaily = $days > 0 ? (int)($mealAllowance / $days) : 100000;
        $transportCost = (float)($claim?->toll_cost ?? 0) + (float)($claim?->fuel_cost ?? 0) + (float)($claim?->car_rental_cost ?? 0);
        if ($transportCost == 0 && !$claim) {
            $transportCost = 200000;
        }
        $lodgingCost = (float)($claim?->lodging_allowance ?? 0);
        $totalCost = $mealAllowance + $transportCost + $lodgingCost;

        // 1. Header Section with Company Logo
        $logoPath = public_path('images/company-logo.png');
        if (file_exists($logoPath)) {
            $drawing = new Drawing();
            $drawing->setName('Logo Perusahaan');
            $drawing->setDescription('Logo PT. Media Selular Indonesia');
            $drawing->setPath($logoPath);
            $drawing->setHeight(48);
            $drawing->setCoordinates('B2');
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        }

        $sheet->mergeCells('B2:F2');
        $sheet->setCellValue('B2', 'FORM PENGAJUAN PERJALANAN DINAS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B3:F3');
        $sheet->setCellValue('B3', 'PT. MEDIA SELULAR INDONESIA');
        $sheet->getStyle('B3')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B4:F4');
        $sheet->setCellValue('B4', 'PUSAT GROSIR & ECERAN');
        $sheet->getStyle('B4')->getFont()->setSize(10)->setItalic(true);
        $sheet->getStyle('B4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('B5', 'Jl. Tuparev No. 109F Kertawinangun');
        $sheet->setCellValue('F5', "Tanggal : {$claimDate}");
        $sheet->getStyle('F5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->setCellValue('B6', 'Kedawung - Cirebon');

        // Divider Line
        $sheet->getStyle('B6:F6')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

        // 2. Data Pemohon Block (Row 8-12)
        $sheet->setCellValue('B8', 'Nama');
        $sheet->setCellValue('C8', ':');
        $sheet->setCellValue('D8', strtoupper($empName));
        $sheet->mergeCells('D8:F8');
        $sheet->getStyle('B8')->getFont()->setBold(true);

        $sheet->setCellValue('B9', 'Bagian');
        $sheet->setCellValue('C9', ':');
        $sheet->setCellValue('D9', 'DISTRIBUSI SALES');
        $sheet->mergeCells('D9:F9');
        $sheet->getStyle('B9')->getFont()->setBold(true);

        $sheet->setCellValue('B10', 'Jabatan');
        $sheet->setCellValue('C10', ':');
        $sheet->setCellValue('D10', strtoupper($empPosition));
        $sheet->mergeCells('D10:F10');
        $sheet->getStyle('B10')->getFont()->setBold(true);

        $sheet->setCellValue('B11', 'Kota Tujuan');
        $sheet->setCellValue('C11', ':');
        $sheet->setCellValue('D11', strtoupper($destination));
        $sheet->mergeCells('D11:F11');
        $sheet->getStyle('B11')->getFont()->setBold(true);

        $sheet->setCellValue('B12', 'Tujuan Perdin');
        $sheet->setCellValue('C12', ':');
        $sheet->setCellValue('D12', strtoupper($purpose));
        $sheet->mergeCells('D12:F12');
        $sheet->getStyle('B12')->getFont()->setBold(true);

        // 3. Rencana Perjalanan Dinas (Row 14-17)
        $sheet->setCellValue('B14', 'Rencana Perjalanan Dinas');
        $sheet->getStyle('B14')->getFont()->setBold(true)->setUnderline(true);

        $sheet->setCellValue('B15', 'Tanggal Keberangkatan');
        $sheet->setCellValue('C15', ':');
        $sheet->setCellValue('D15', $tglBerangkat);

        $sheet->setCellValue('B16', 'Jumlah hari');
        $sheet->setCellValue('C16', ':');
        $sheet->setCellValue('D16', "{$days} Hari");

        $sheet->setCellValue('B17', 'Tanggal Kepulangan');
        $sheet->setCellValue('C17', ':');
        $sheet->setCellValue('D17', $tglPulang);

        // 4. Rencana Biaya Perjalanan Dinas (Row 19-25)
        $sheet->setCellValue('B19', 'Rencana Biaya Perjalanan Dinas');
        $sheet->getStyle('B19')->getFont()->setBold(true)->setUnderline(true);

        // Table Header Biaya
        $sheet->setCellValue('B20', 'Rincian Biaya');
        $sheet->setCellValue('D20', 'Qty');
        $sheet->setCellValue('E20', 'Budget');
        $sheet->setCellValue('F20', 'Jumlah');

        $sheet->getStyle('B20:F20')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        // Item 1: Tunjangan PerDin
        $sheet->setCellValue('B21', '1. Tunjangan PerDin');
        $sheet->setCellValue('C21', ':');
        $sheet->setCellValue('D21', $days);
        $sheet->setCellValue('E21', $mealDaily);
        $sheet->setCellValue('F21', $mealAllowance);

        // Item 2: Transportasi
        $sheet->setCellValue('B22', '2. Transportasi (Tol/BBM)');
        $sheet->setCellValue('C22', ':');
        $sheet->setCellValue('D22', 1);
        $sheet->setCellValue('E22', $transportCost);
        $sheet->setCellValue('F22', $transportCost);

        // Item 3: Entertainment
        $sheet->setCellValue('B23', '3. Entertainment');
        $sheet->setCellValue('C23', ':');
        $sheet->setCellValue('D23', '-');
        $sheet->setCellValue('E23', '-');
        $sheet->setCellValue('F23', 0);

        // Item 4: Penginapan (jika ada)
        $sheet->setCellValue('B24', '4. Penginapan');
        $sheet->setCellValue('C24', ':');
        $sheet->setCellValue('D24', ($claim?->nights_count ?? 0) > 0 ? $claim->nights_count : '-');
        $sheet->setCellValue('E24', $lodgingCost > 0 ? (int)($lodgingCost / max(1, $claim?->nights_count ?? 1)) : '-');
        $sheet->setCellValue('F24', $lodgingCost);

        // Number Formats
        foreach ([21, 22, 23, 24] as $r) {
            $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("F{$r}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        }

        // Total Row
        $sheet->setCellValue('B26', 'Total Biaya');
        $sheet->setCellValue('F26', "=SUM(F21:F24)");
        $sheet->getStyle('B26:F26')->applyFromArray([
            'font' => ['bold' => true],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
        ]);
        $sheet->getStyle('F26')->getNumberFormat()->setFormatCode('"Rp "#,##0');

        // 5. Signature Section (Row 28-34)
        $sheet->setCellValue('B28', 'di ajukan oleh :');
        $sheet->setCellValue('E28', 'di setujui oleh :');
        $sheet->getStyle('B28')->getFont()->setItalic(true);
        $sheet->getStyle('E28')->getFont()->setItalic(true);

        // Check if employee has digital signature
        $sigImage = $emp?->signature_image;
        if ($sigImage && Storage::disk('public')->exists($sigImage)) {
            $sigFullPath = Storage::disk('public')->path($sigImage);
            $drawing = new Drawing();
            $drawing->setName('Tanda Tangan Pemohon');
            $drawing->setPath($sigFullPath);
            $drawing->setHeight(50);
            $drawing->setCoordinates('B30');
            $drawing->setWorksheet($sheet);
        }

        $sheet->setCellValue('B34', "( {$empName} )");
        $sheet->setCellValue('E34', "( Pak Jejen / Management )");
        $sheet->getStyle('B34')->getFont()->setBold(true);
        $sheet->getStyle('E34')->getFont()->setBold(true);

        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName);
        $filename = "Form_Perdin_{$cleanName}_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export recap of Entertain budget & remaining balance for all employees
     */
    public function exportEntertainBudgetRecap(Collection $employees): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Budget Entertain');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(4);

        // Title Header
        $sheet->mergeCells('B1:H1');
        $sheet->setCellValue('B1', 'REKAPITULASI BIAYA & SISA BUDGET ENTERTAIN KARYAWAN');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B2:H2');
        $sheet->setCellValue('B2', 'Periode Cetak: ' . date('d F Y'));
        $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB('64748B');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Table Header (Row 4)
        $headerRow = 4;
        $headers = [
            'B' => 'NO',
            'C' => 'NAMA KARYAWAN',
            'D' => 'JABATAN',
            'E' => 'PLAFON BUDGET ENTERTAIN',
            'F' => 'REALISASI ENTERTAIN TERPAKAI',
            'G' => 'SISA SALDO ENTERTAIN',
            'H' => 'STATUS SISA',
        ];

        foreach ($headers as $col => $headerText) {
            $sheet->setCellValue("{$col}{$headerRow}", $headerText);
        }

        $headerRange = "B{$headerRow}:H{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(25);

        // Data Rows
        $currentRow = 5;
        $no = 1;

        foreach ($employees as $emp) {
            $empName = $emp->name;
            $positionName = $emp->position_name;
            $plafonEntertain = (float)($emp->entertain_budget > 0 ? $emp->entertain_budget : ($emp->positionModel?->entertain_budget ?? 0));

            // Calculate used entertain claims (specifically Makan sub-type)
            $usedEntertain = 0;
            foreach ($emp->claims as $claim) {
                foreach ($claim->getLineItems() as $item) {
                    $itemType = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? '');
                    $subtype = $item['entertain_subtype'] ?? ($claim->entertain_subtype ?? 'Makan');
                    if (str_contains(strtoupper($itemType), 'ENTERTAIN') && strtoupper($subtype) !== 'LAINNYA') {
                        $usedEntertain += (float)($item['amount'] ?? 0);
                    }
                }
            }

            $sisaSaldo = $plafonEntertain - $usedEntertain;

            if ($plafonEntertain <= 0) {
                $status = 'Tanpa Plafon';
            } elseif ($sisaSaldo < 0) {
                $status = 'Over Budget (' . number_format(abs($sisaSaldo), 0, ',', '.') . ')';
            } elseif ($sisaSaldo == 0) {
                $status = 'Habis (Pas)';
            } else {
                $status = 'Sisa Aman';
            }

            $sheet->setCellValue("B{$currentRow}", $no);
            $sheet->setCellValue("C{$currentRow}", $empName);
            $sheet->setCellValue("D{$currentRow}", $positionName);
            $sheet->setCellValue("E{$currentRow}", $plafonEntertain);
            $sheet->setCellValue("F{$currentRow}", $usedEntertain);
            $sheet->setCellValue("G{$currentRow}", $sisaSaldo);
            $sheet->setCellValue("H{$currentRow}", $status);

            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($sisaSaldo < 0) {
                $sheet->getStyle("H{$currentRow}")->getFont()->setBold(true)->getColor()->setRGB('DC2626');
            } elseif ($sisaSaldo > 0 && $plafonEntertain > 0) {
                $sheet->getStyle("H{$currentRow}")->getFont()->setBold(true)->getColor()->setRGB('16A34A');
            }

            if ($currentRow % 2 === 0) {
                $sheet->getStyle("B{$currentRow}:H{$currentRow}")->getFill()->applyFromArray([
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFC'],
                ]);
            }

            $currentRow++;
            $no++;
        }

        // Total Row
        $sheet->mergeCells("B{$currentRow}:D{$currentRow}");
        $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN');
        $sheet->setCellValue("E{$currentRow}", "=SUM(E{$headerRow}:E" . ($currentRow - 1) . ")");
        $sheet->setCellValue("F{$currentRow}", "=SUM(F{$headerRow}:F" . ($currentRow - 1) . ")");
        $sheet->setCellValue("G{$currentRow}", "=SUM(G{$headerRow}:G" . ($currentRow - 1) . ")");
        $sheet->setCellValue("H{$currentRow}", "-");

        $sheet->getStyle("E{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $totalRange = "B{$currentRow}:H{$currentRow}";
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        $tableRange = "B{$headerRow}:H{$currentRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        foreach (range('B', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Rekap_Budget_Entertain_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Rekapan Sisa Klaim dengan Template Khusus
     */
    public function exportSisaKlaimRecapTemplate(Collection $claims, ?Collection $employees = null, ?string $selectedMonth = null, ?string $selectedYear = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Sisa Klaim');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(3);

        // 1. Header Plafon Referensi Role (RGM: 2.400.000, ASM: 1.500.000, etc.)
        $rgmRole = Role::where('code', 'RGM')->first();
        $asmRole = Role::where('code', 'ASM')->first();
        $rgmBudget = $rgmRole ? (float)$rgmRole->entertain_budget : 2400000;
        $asmBudget = $asmRole ? (float)$asmRole->entertain_budget : 1500000;

        $sheet->setCellValue('B2', 'RGM');
        $sheet->setCellValue('C2', $rgmBudget);
        $sheet->getStyle('B2')->getFont()->setBold(true);
        $sheet->getStyle('C2')->getFont()->setBold(true);
        $sheet->getStyle('C2')->getNumberFormat()->setFormatCode('"Rp "#,##0');

        $sheet->setCellValue('B3', 'ASM');
        $sheet->setCellValue('C3', $asmBudget);
        $sheet->getStyle('B3')->getFont()->setBold(true);
        $sheet->getStyle('C3')->getFont()->setBold(true);
        $sheet->getStyle('C3')->getNumberFormat()->setFormatCode('"Rp "#,##0');

        $sheet->getStyle('B2:C3')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        // 2. Main Detail Table Header (Row 5)
        $mainHeaderRow = 5;
        $mainHeaders = [
            'B' => 'TANGGAL PENGAJUAN',
            'C' => 'NAMA',
            'D' => 'ROLE',
            'E' => 'AREA',
            'F' => 'PENGAJUAN',
            'G' => 'JENIS BUDGET',
            'H' => 'TANGGAL NOTA',
            'I' => 'KET',
            'J' => 'NOMINAL',
            'K' => 'REFF NOTE',
            'L' => 'NOTA FISIK',
            'M' => 'PROGRESS',
        ];

        foreach ($mainHeaders as $col => $headerText) {
            $sheet->setCellValue("{$col}{$mainHeaderRow}", $headerText);
        }

        $mainHeaderRange = "B{$mainHeaderRow}:M{$mainHeaderRow}";
        $sheet->getStyle($mainHeaderRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($mainHeaderRow)->setRowHeight(26);

        // 3. Main Detail Data Rows
        $currentRow = $mainHeaderRow + 1;
        $cumulativePerEmployee = [];

        foreach ($claims as $claim) {
            $emp = $claim->effective_employee;
            $empName = $emp?->name ?? 'Karyawan';
            $empRole = $emp?->position_name ?? 'RGM';
            $area = $claim->city ?: ($claim->branch?->city ?? 'Purwokerto');
            $submissionDate = $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-';
            $bulanStr = $claim->claim_date ? $claim->claim_date->translatedFormat('F Y') : date('F Y');
            $reffnote = $claim->reffnote ?: ($claim->branch?->reffnote ?? '-');

            foreach ($claim->getLineItems() as $item) {
                $itemType = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? 'Entertain');
                $itemPurpose = is_array($item['purpose'] ?? null) ? implode(', ', $item['purpose']) : ($item['purpose'] ?? '-');
                $itemNote = $item['note'] ?? '-';
                $itemReffnote = $item['reffnote'] ?? $reffnote;
                $itemAmount = (float)($item['amount'] ?? 0);

                // NOTA FISIK: cek apakah ada file foto (bbm_photo_before, photos, atau attachment)
                $hasPhysicalReceipt = false;
                if (!empty($item['bbm_photo_before']) || !empty($item['bbm_photo_after'])) {
                    $hasPhysicalReceipt = true;
                } elseif (!empty($item['photos']) && is_array($item['photos']) && count($item['photos']) > 0) {
                    $hasPhysicalReceipt = true;
                } elseif ($claim->bbm_photo_before || $claim->photos) {
                    $hasPhysicalReceipt = true;
                }
                $notaFisikStr = $hasPhysicalReceipt ? '✓' : '-';

                // PROGRESS: status pencairan atau approval
                $progressStr = $claim->disbursement_status === 'Sudah Dicairkan'
                    ? 'CAIR'
                    : match ($claim->approval_status ?? '') {
                        'DISETUJUI', 'ACC_PAK_JEJEN', 'ACC_RGM' => 'DISETUJUI',
                        'DITOLAK' => 'DITOLAK',
                        'ACC_ASM' => 'ACC ASM',
                        'DIAJUKAN' => 'DIPROSES',
                        'DRAFT' => 'DRAFT',
                        default => $claim->approval_status ?? 'DIPROSES',
                    };

                $tglNota = !empty($item['receipt_date']) ? date('d/m/Y', strtotime($item['receipt_date'])) : $submissionDate;

                $sheet->setCellValue("B{$currentRow}", $submissionDate);
                $sheet->setCellValue("C{$currentRow}", $empName);
                $sheet->setCellValue("D{$currentRow}", $empRole);
                $sheet->setCellValue("E{$currentRow}", $area);
                $sheet->setCellValue("F{$currentRow}", $itemPurpose);
                $sheet->setCellValue("G{$currentRow}", $itemType);
                $sheet->setCellValue("H{$currentRow}", $tglNota);
                $sheet->setCellValue("I{$currentRow}", $itemNote);
                $sheet->setCellValue("J{$currentRow}", $itemAmount);
                $sheet->setCellValue("K{$currentRow}", $itemReffnote);
                $sheet->setCellValue("L{$currentRow}", $notaFisikStr);
                $sheet->setCellValue("M{$currentRow}", $progressStr);

                $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("J{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet->getStyle("K{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("L{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("L{$currentRow}")->getFont()->setBold(true);
                if ($notaFisikStr === '✓') {
                    $sheet->getStyle("L{$currentRow}")->getFont()->getColor()->setRGB('16A34A');
                }
                $sheet->getStyle("M{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Progress color coding
                if ($progressStr === 'CAIR') {
                    $sheet->getStyle("M{$currentRow}")->getFont()->setBold(true)->getColor()->setRGB('16A34A');
                } elseif ($progressStr === 'DITOLAK') {
                    $sheet->getStyle("M{$currentRow}")->getFont()->setBold(true)->getColor()->setRGB('DC2626');
                } elseif ($progressStr === 'DISETUJUI') {
                    $sheet->getStyle("M{$currentRow}")->getFont()->getColor()->setRGB('0369A1');
                }

                if ($currentRow % 2 === 0) {
                    $sheet->getStyle("B{$currentRow}:M{$currentRow}")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ]);
                }

                $currentRow++;
            }
        }

        // Total Row for Main Table
        $sheet->mergeCells("B{$currentRow}:I{$currentRow}");
        $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN');
        $sheet->setCellValue("J{$currentRow}", "=SUM(J{$mainHeaderRow}:J" . ($currentRow - 1) . ")");
        $sheet->setCellValue("K{$currentRow}", "-");
        $sheet->setCellValue("L{$currentRow}", "-");
        $sheet->setCellValue("M{$currentRow}", "-");

        $sheet->getStyle("J{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');

        $mainTotalRange = "B{$currentRow}:M{$currentRow}";
        $sheet->getStyle($mainTotalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        $mainTableRange = "B{$mainHeaderRow}:M{$currentRow}";
        $sheet->getStyle($mainTableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        // 4. Bottom Summary Table
        $summaryStartRow = $currentRow + 3;
        $sheet->setCellValue("B{$summaryStartRow}", 'RINGKASAN SISA BUDGET KARYAWAN');
        $sheet->getStyle("B{$summaryStartRow}")->getFont()->setBold(true)->setSize(11);

        $summaryHeaderRow = $summaryStartRow + 1;
        $summaryHeaders = [
            'B' => 'NAMA',
            'C' => 'ROLE',
            'D' => 'BUDGET',
            'E' => 'TERPAKAI',
            'F' => 'SISA BUDGET',
        ];

        foreach ($summaryHeaders as $col => $text) {
            $sheet->setCellValue("{$col}{$summaryHeaderRow}", $text);
        }

        $sumHeaderRange = "B{$summaryHeaderRow}:F{$summaryHeaderRow}";
        $sheet->getStyle($sumHeaderRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($summaryHeaderRow)->setRowHeight(24);

        $sumCurrentRow = $summaryHeaderRow + 1;
        $empList = $employees ?: Employee::with(['positionModel', 'role', 'claims'])->get();

        foreach ($empList as $empItem) {
            $eName = $empItem->name;
            $eRole = $empItem->position_name;
            $eBudget = (float)$empItem->total_budget;
            $eTerpakai = isset($cumulativePerEmployee[$eName]) 
                ? (float)$cumulativePerEmployee[$eName] 
                : (float)$empItem->getTotalExpenseUsedForPeriod($selectedMonth ? (int)$selectedMonth : null, $selectedYear ? (int)$selectedYear : null);
            $eSisa = $eBudget - $eTerpakai;

            $sheet->setCellValue("B{$sumCurrentRow}", $eName);
            $sheet->setCellValue("C{$sumCurrentRow}", $eRole);
            $sheet->setCellValue("D{$sumCurrentRow}", $eBudget);
            $sheet->setCellValue("E{$sumCurrentRow}", $eTerpakai);
            $sheet->setCellValue("F{$sumCurrentRow}", $eSisa);

            $sheet->getStyle("C{$sumCurrentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$sumCurrentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("E{$sumCurrentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("F{$sumCurrentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');

            if ($eSisa < 0) {
                $sheet->getStyle("F{$sumCurrentRow}")->getFont()->setBold(true)->getColor()->setRGB('DC2626');
            } elseif ($eSisa > 0 && $eBudget > 0) {
                $sheet->getStyle("F{$sumCurrentRow}")->getFont()->setBold(true)->getColor()->setRGB('16A34A');
            }

            if ($sumCurrentRow % 2 === 0) {
                $sheet->getStyle("B{$sumCurrentRow}:F{$sumCurrentRow}")->getFill()->applyFromArray([
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFC'],
                ]);
            }

            $sumCurrentRow++;
        }

        // Summary Total Row
        $sheet->setCellValue("B{$sumCurrentRow}", 'TOTAL');
        $sheet->setCellValue("C{$sumCurrentRow}", '-');
        $sheet->setCellValue("D{$sumCurrentRow}", "=SUM(D{$summaryHeaderRow}:D" . ($sumCurrentRow - 1) . ")");
        $sheet->setCellValue("E{$sumCurrentRow}", "=SUM(E{$summaryHeaderRow}:E" . ($sumCurrentRow - 1) . ")");
        $sheet->setCellValue("F{$sumCurrentRow}", "=SUM(F{$summaryHeaderRow}:F" . ($sumCurrentRow - 1) . ")");

        $sheet->getStyle("C{$sumCurrentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$sumCurrentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("E{$sumCurrentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("F{$sumCurrentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');

        $sumTotalRange = "B{$sumCurrentRow}:F{$sumCurrentRow}";
        $sheet->getStyle($sumTotalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle("B{$sumCurrentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($sumCurrentRow)->setRowHeight(22);

        $summaryTableRange = "B{$summaryHeaderRow}:F{$sumCurrentRow}";
        $sheet->getStyle($summaryTableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        foreach (range('B', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Rekapan_Sisa_Klaim_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Rekapan Biaya BBM Excel
     */
    public function exportBbmRecapExcel(Collection $claims): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Biaya BBM');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(3);

        // Header Title
        $sheet->mergeCells('B1:R1');
        $sheet->setCellValue('B1', 'REKAPITULASI BIAYA PENGISIAN BBM OPERASIONAL');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B2:R2');
        $sheet->setCellValue('B2', 'Periode Cetak: ' . date('d F Y'));
        $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB('64748B');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 4;
        $headers = [
            'B' => 'NO',
            'C' => 'BULAN',
            'D' => 'TGL PENGAJUAN',
            'E' => 'TGL NOTA BBM',
            'F' => '_UID',
            'G' => 'NAMA PEMOHON',
            'H' => 'ROLE',
            'I' => 'AREA / CABANG',
            'J' => 'SPBU / LOKASI',
            'K' => 'KENDARAAN',
            'L' => 'KM AWAL',
            'M' => 'JENIS BBM',
            'N' => 'VOLUME (LITER)',
            'O' => 'NOTA SPBU',
            'P' => 'TAMBAHAN +5K',
            'Q' => 'TOTAL KLAIM',
            'R' => 'STATUS PENCAIRAN',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue("{$col}{$headerRow}", $text);
        }

        $headerRange = "B{$headerRow}:R{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(25);

        $currentRow = 5;
        $no = 1;

        foreach ($claims as $claim) {
            $emp = $claim->effective_employee;
            $bulanStr = $claim->claim_date ? $claim->claim_date->translatedFormat('F Y') : date('F Y');
            $tglStr = $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-';
            $empName = $emp?->name ?? 'Karyawan';
            $empRole = $emp?->position_name ?? 'ASM';
            $area = $claim->city ?: ($claim->branch?->name ?? 'Purwokerto');
            $uid = $claim->_uid ?? '-';

            foreach ($claim->getLineItems() as $item) {
                $itemType = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? 'BBM');
                if (!str_contains(strtoupper($itemType), 'BBM') && $claim->claim_category !== 'bbm') {
                    continue;
                }

                $tglNota = !empty($item['receipt_date']) ? date('d/m/Y', strtotime($item['receipt_date'])) : $tglStr;
                $veh = $item['vehicle_type'] ?? ($claim->vehicle_type ?? 'Mobil');
                $km = !empty($item['fuel_start_km']) ? number_format((float)$item['fuel_start_km'], 0, ',', '.') . ' KM' : ($claim->fuel_start_km ? number_format((float)$claim->fuel_start_km, 0, ',', '.') . ' KM' : '-');
                $fuel = $item['fuel_type'] ?? ($claim->fuel_type ?? 'Pertalite');
                $spbu = $item['note'] ?? ($claim->note ?? 'SPBU');
                $base = (float)($item['fuel_base_amount'] ?? ($claim->fuel_base_amount ?? ($item['amount'] ?? 0)));
                $liters = (float)($item['fuel_liters'] ?? ($claim->fuel_liters ?? ($base > 0 ? round($base / 10000, 2) : 0)));
                $extra = $veh === 'Mobil' ? 5000 : 0;
                $tot = (float)($item['amount'] ?? $claim->amount);
                $disburse = $claim->disbursement_status ?? 'Belum Dicairkan';

                $sheet->setCellValue("B{$currentRow}", $no);
                $sheet->setCellValue("C{$currentRow}", strtoupper($bulanStr));
                $sheet->setCellValue("D{$currentRow}", $tglStr);
                $sheet->setCellValue("E{$currentRow}", $tglNota);
                $sheet->setCellValue("F{$currentRow}", $uid);
                $sheet->setCellValue("G{$currentRow}", $empName);
                $sheet->setCellValue("H{$currentRow}", $empRole);
                $sheet->setCellValue("I{$currentRow}", $area);
                $sheet->setCellValue("J{$currentRow}", $spbu);
                $sheet->setCellValue("K{$currentRow}", $veh);
                $sheet->setCellValue("L{$currentRow}", $km);
                $sheet->setCellValue("M{$currentRow}", $fuel);
                $sheet->setCellValue("N{$currentRow}", $liters);
                $sheet->setCellValue("O{$currentRow}", $base);
                $sheet->setCellValue("P{$currentRow}", $extra);
                $sheet->setCellValue("Q{$currentRow}", $tot);
                $sheet->setCellValue("R{$currentRow}", $disburse);

                $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("K{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("L{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("M{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("N{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("O{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet->getStyle("P{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet->getStyle("Q{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet->getStyle("R{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($currentRow % 2 === 0) {
                    $sheet->getStyle("B{$currentRow}:R{$currentRow}")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ]);
                }

                $currentRow++;
                $no++;
            }
        }

        // Total Row
        $sheet->mergeCells("B{$currentRow}:N{$currentRow}");
        $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN');
        $sheet->setCellValue("O{$currentRow}", "=SUM(O{$headerRow}:O" . ($currentRow - 1) . ")");
        $sheet->setCellValue("P{$currentRow}", "=SUM(P{$headerRow}:P" . ($currentRow - 1) . ")");
        $sheet->setCellValue("Q{$currentRow}", "=SUM(Q{$headerRow}:Q" . ($currentRow - 1) . ")");
        $sheet->setCellValue("R{$currentRow}", "-");

        $sheet->getStyle("O{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("P{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle("Q{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');

        $totalRange = "B{$currentRow}:R{$currentRow}";
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        $tableRange = "B{$headerRow}:R{$currentRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        foreach (range('B', 'R') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Create Sheet 2 for All BBM Documentation Photos & Return Transfer Proofs
        $photoSheet = $spreadsheet->createSheet();
        $photoSheet->setTitle('Lampiran Foto BBM');
        $photoSheet->getColumnDimension('A')->setWidth(4);
        $photoSheet->mergeCells('B1:I1');
        $photoSheet->setCellValue('B1', 'LAMPIRAN FOTO DOKUMENTASI BBM & BUKTI PENGEMBALIAN DANA');
        $photoSheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $photoSheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $pRow = 3;
        foreach ($claims as $c) {
            $pEmp = $c->effective_employee;
            $pEmpName = $pEmp?->name ?? 'Karyawan';
            $pEmpRole = $pEmp?->position_name ?? 'ASM';
            $pDate = $c->claim_date ? $c->claim_date->format('d/m/Y') : '-';
            $pUid = $c->_uid ?: '-';
            $pTotal = 'Rp ' . number_format((float)$c->amount, 0, ',', '.');

            $claimHeader = "KLAIM BBM: {$pEmpName} ({$pEmpRole}) | TGL: {$pDate} | _UID: {$pUid} | TOTAL: {$pTotal}";
            $photoSheet->mergeCells("B{$pRow}:I{$pRow}");
            $photoSheet->setCellValue("B{$pRow}", $claimHeader);
            $photoSheet->getStyle("B{$pRow}:I{$pRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E293B'],
                ],
            ]);
            $pRow += 2;

            $pRow = $this->embedClaimPhotosToWorksheet($photoSheet, $c, $pRow, false);
            $pRow += 2;
        }

        $sheet->setCellValue("B" . ($currentRow + 2), '* Seluruh foto dokumentasi BBM & bukti transfer pengembalian dana telah dimasukkan lengkap pada Sheet "Lampiran Foto BBM".');
        $sheet->getStyle("B" . ($currentRow + 2))->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F766E'));

        $filename = "Rekap_Biaya_BBM_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Rekapan Biaya Entertain Excel
     */
    public function exportEntertainRecapExcel(Collection $claims): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Biaya Entertain');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(3);

        // Header Title
        $sheet->mergeCells('B1:N1');
        $sheet->setCellValue('B1', 'REKAPITULASI BIAYA ENTERTAIN & TRANSPORTASI (ENTERTAIN, TOL, PARKIR, TIKET)');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B2:N2');
        $sheet->setCellValue('B2', 'Periode Cetak: ' . date('d F Y'));
        $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB('64748B');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 4;
        $headers = [
            'B' => 'NO',
            'C' => 'BULAN',
            'D' => 'TGL PENGAJUAN',
            'E' => 'TGL NOTA',
            'F' => '_UID',
            'G' => 'NAMA PEMOHON',
            'H' => 'ROLE',
            'I' => 'AREA / KOTA',
            'J' => 'JENIS BIAYA',
            'K' => 'KEPERLUAN / AGENDA',
            'L' => 'LOKASI / TEMPAT',
            'M' => 'NOMINAL BIAYA',
            'N' => 'STATUS PENCAIRAN',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue("{$col}{$headerRow}", $text);
        }

        $headerRange = "B{$headerRow}:N{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(25);

        $currentRow = 5;
        $no = 1;

        foreach ($claims as $claim) {
            $emp = $claim->effective_employee;
            $bulanStr = $claim->claim_date ? $claim->claim_date->translatedFormat('F Y') : date('F Y');
            $tglStr = $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-';
            $empName = $emp?->name ?? 'Karyawan';
            $empRole = $emp?->position_name ?? 'ASM';
            $area = $claim->city ?: ($claim->branch?->city ?? 'Purwokerto');
            $uid = $claim->_uid ?? '-';

            foreach ($claim->getLineItems() as $item) {
                $itemType = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? 'Entertain');
                $upperType = strtoupper($itemType);

                // Exclude BBM and Perdin items
                if (str_contains($upperType, 'BBM') || str_contains($upperType, 'BENSIN') || str_contains($upperType, 'PERDIN')) {
                    continue;
                }

                $tglNota = !empty($item['receipt_date']) ? date('d/m/Y', strtotime($item['receipt_date'])) : $tglStr;
                $subtype = $item['entertain_subtype'] ?? ($claim->entertain_subtype ?? 'Makan');
                $purpose = is_array($item['purpose'] ?? null) ? implode(', ', $item['purpose']) : ($item['purpose'] ?? '-');
                $note = $item['note'] ?? '-';
                $amount = (float)($item['amount'] ?? 0);
                $disburse = $claim->disbursement_status ?? 'Belum Dicairkan';

                $kategoriLabel = str_contains($upperType, 'ENTERTAIN')
                    ? "Entertain ({$subtype})"
                    : $itemType;

                $sheet->setCellValue("B{$currentRow}", $no);
                $sheet->setCellValue("C{$currentRow}", strtoupper($bulanStr));
                $sheet->setCellValue("D{$currentRow}", $tglStr);
                $sheet->setCellValue("E{$currentRow}", $tglNota);
                $sheet->setCellValue("F{$currentRow}", $uid);
                $sheet->setCellValue("G{$currentRow}", $empName);
                $sheet->setCellValue("H{$currentRow}", $empRole);
                $sheet->setCellValue("I{$currentRow}", $area);
                $sheet->setCellValue("J{$currentRow}", $kategoriLabel);
                $sheet->setCellValue("K{$currentRow}", $purpose);
                $sheet->setCellValue("L{$currentRow}", $note);
                $sheet->setCellValue("M{$currentRow}", $amount);
                $sheet->setCellValue("N{$currentRow}", $disburse);

                $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("J{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("M{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet->getStyle("N{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($currentRow % 2 === 0) {
                    $sheet->getStyle("B{$currentRow}:N{$currentRow}")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ]);
                }

                $currentRow++;
                $no++;
            }
        }

        // Total Row
        $sheet->mergeCells("B{$currentRow}:L{$currentRow}");
        $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN BIAYA ENTERTAIN & TRANSPORTASI');
        $sheet->setCellValue("M{$currentRow}", "=SUM(M{$headerRow}:M" . ($currentRow - 1) . ")");
        $sheet->setCellValue("N{$currentRow}", "-");

        $sheet->getStyle("M{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');

        $totalRange = "B{$currentRow}:N{$currentRow}";
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        $tableRange = "B{$headerRow}:N{$currentRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        foreach (range('B', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Rekap_Biaya_Entertain_Dan_Transportasi_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Rekapan Biaya Perjalanan Dinas (Perdin) Excel
     */
    public function exportPerdinRecapExcel(Collection $claims): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Perjalanan Dinas');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(3);

        // Header Title
        $sheet->mergeCells('B1:Q1');
        $sheet->setCellValue('B1', 'REKAPITULASI BIAYA PERJALANAN DINAS (PERDIN)');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B2:Q2');
        $sheet->setCellValue('B2', 'Periode Cetak: ' . date('d F Y'));
        $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB('64748B');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 4;
        $headers = [
            'B' => 'NO',
            'C' => 'TGL BERANGKAT',
            'D' => '_UID',
            'E' => 'NAMA PEMOHON',
            'F' => 'ROLE',
            'G' => 'KOTA ASAL',
            'H' => 'KOTA TUJUAN',
            'I' => 'JARAK (KM)',
            'J' => 'DURASI',
            'K' => 'UANG MAKAN',
            'L' => 'PENGINAPAN',
            'M' => 'TOL',
            'N' => 'BENSIN / BBM',
            'O' => 'SEWA/SERVICE',
            'P' => 'TOTAL BIAYA',
            'Q' => 'STATUS PENCAIRAN',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue("{$col}{$headerRow}", $text);
        }

        $headerRange = "B{$headerRow}:Q{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(25);

        $currentRow = 5;
        $no = 1;

        foreach ($claims as $claim) {
            if (!$claim->is_perdin && $claim->claim_category !== 'perdin') {
                continue;
            }

            $emp = $claim->effective_employee;
            $tglStr = $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-';
            $empName = $emp?->name ?? 'Karyawan';
            $empRole = $emp?->position_name ?? 'ASM';
            $homebase = $claim->homebase ?: 'Purwokerto';
            $dest = $claim->destination_city ?: 'Tujuan';
            $dist = (float)($claim->distance_km ?? 80);
            $durasi = "{$claim->days_count} Hari / {$claim->nights_count} Malam";
            $meal = (float)$claim->meal_allowance;
            $lodge = (float)$claim->lodging_allowance;
            $toll = (float)$claim->toll_cost;
            $fuel = (float)$claim->fuel_cost;
            $other = (float)$claim->car_rental_cost + (float)$claim->service_cost;
            $total = (float)$claim->amount;
            $disburse = $claim->disbursement_status ?? 'Belum Dicairkan';
            $uid = $claim->_uid ?? '-';

            $sheet->setCellValue("B{$currentRow}", $no);
            $sheet->setCellValue("C{$currentRow}", $tglStr);
            $sheet->setCellValue("D{$currentRow}", $uid);
            $sheet->setCellValue("E{$currentRow}", $empName);
            $sheet->setCellValue("F{$currentRow}", $empRole);
            $sheet->setCellValue("G{$currentRow}", $homebase);
            $sheet->setCellValue("H{$currentRow}", $dest);
            $sheet->setCellValue("I{$currentRow}", $dist);
            $sheet->setCellValue("J{$currentRow}", $durasi);
            $sheet->setCellValue("K{$currentRow}", $meal);
            $sheet->setCellValue("L{$currentRow}", $lodge);
            $sheet->setCellValue("M{$currentRow}", $toll);
            $sheet->setCellValue("N{$currentRow}", $fuel);
            $sheet->setCellValue("O{$currentRow}", $other);
            $sheet->setCellValue("P{$currentRow}", $total);
            $sheet->setCellValue("Q{$currentRow}", $disburse);

            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("L{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("M{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("N{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("O{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("P{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("Q{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($currentRow % 2 === 0) {
                $sheet->getStyle("B{$currentRow}:Q{$currentRow}")->getFill()->applyFromArray([
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFC'],
                ]);
            }

            $currentRow++;
            $no++;
        }

        // Total Row
        $sheet->mergeCells("B{$currentRow}:J{$currentRow}");
        $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN BIAYA PERDIN');
        $sheet->setCellValue("K{$currentRow}", "=SUM(K{$headerRow}:K" . ($currentRow - 1) . ")");
        $sheet->setCellValue("L{$currentRow}", "=SUM(L{$headerRow}:L" . ($currentRow - 1) . ")");
        $sheet->setCellValue("M{$currentRow}", "=SUM(M{$headerRow}:M" . ($currentRow - 1) . ")");
        $sheet->setCellValue("N{$currentRow}", "=SUM(N{$headerRow}:N" . ($currentRow - 1) . ")");
        $sheet->setCellValue("O{$currentRow}", "=SUM(O{$headerRow}:O" . ($currentRow - 1) . ")");
        $sheet->setCellValue("P{$currentRow}", "=SUM(P{$headerRow}:P" . ($currentRow - 1) . ")");
        $sheet->setCellValue("Q{$currentRow}", "-");

        foreach (['K', 'L', 'M', 'N', 'O', 'P'] as $c) {
            $sheet->getStyle("{$c}{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        }

        $totalRange = "B{$currentRow}:Q{$currentRow}";
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        $tableRange = "B{$headerRow}:Q{$currentRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        foreach (range('B', 'Q') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Rekap_Biaya_Perdin_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Rekapan Biaya Transportasi Excel
     */
    public function exportTransportRecapExcel(Collection $claims): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Biaya Transportasi');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(3);

        // Header Title
        $sheet->mergeCells('B1:M1');
        $sheet->setCellValue('B1', 'REKAPITULASI BIAYA TRANSPORTASI & OPERASIONAL');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B2:M2');
        $sheet->setCellValue('B2', 'Periode Cetak: ' . date('d F Y'));
        $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB('64748B');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 4;
        $headers = [
            'B' => 'NO',
            'C' => 'BULAN',
            'D' => 'TGL PENGAJUAN',
            'E' => 'TGL NOTA / TIKET',
            'F' => '_UID',
            'G' => 'NAMA PEMOHON',
            'H' => 'ROLE',
            'I' => 'AREA / CABANG',
            'J' => 'JENIS TRANSPORTASI',
            'K' => 'KEPERLUAN PERJALANAN',
            'L' => 'NOMINAL BIAYA',
            'M' => 'STATUS PENCAIRAN',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue("{$col}{$headerRow}", $text);
        }

        $headerRange = "B{$headerRow}:M{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(25);

        $currentRow = 5;
        $no = 1;

        foreach ($claims as $claim) {
            $emp = $claim->effective_employee;
            $bulanStr = $claim->claim_date ? $claim->claim_date->translatedFormat('F Y') : date('F Y');
            $tglStr = $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-';
            $empName = $emp?->name ?? 'Karyawan';
            $empRole = $emp?->position_name ?? 'ASM';
            $area = $claim->city ?: ($claim->branch?->city ?? 'Purwokerto');
            $uid = $claim->_uid ?? '-';

            foreach ($claim->getLineItems() as $item) {
                $itemType = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? 'Transportasi');
                if (!str_contains(strtoupper($itemType), 'TRANSPORT') && !str_contains(strtoupper($itemType), 'TOL') && !str_contains(strtoupper($itemType), 'TIKET')) {
                    continue;
                }

                $tglNota = !empty($item['receipt_date']) ? date('d/m/Y', strtotime($item['receipt_date'])) : $tglStr;
                $purpose = is_array($item['purpose'] ?? null) ? implode(', ', $item['purpose']) : ($item['purpose'] ?? '-');
                $amount = (float)($item['amount'] ?? 0);
                $disburse = $claim->disbursement_status ?? 'Belum Dicairkan';

                $sheet->setCellValue("B{$currentRow}", $no);
                $sheet->setCellValue("C{$currentRow}", strtoupper($bulanStr));
                $sheet->setCellValue("D{$currentRow}", $tglStr);
                $sheet->setCellValue("E{$currentRow}", $tglNota);
                $sheet->setCellValue("F{$currentRow}", $uid);
                $sheet->setCellValue("G{$currentRow}", $empName);
                $sheet->setCellValue("H{$currentRow}", $empRole);
                $sheet->setCellValue("I{$currentRow}", $area);
                $sheet->setCellValue("J{$currentRow}", $itemType);
                $sheet->setCellValue("K{$currentRow}", $purpose);
                $sheet->setCellValue("L{$currentRow}", $amount);
                $sheet->setCellValue("M{$currentRow}", $disburse);

                $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("J{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("L{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet->getStyle("M{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($currentRow % 2 === 0) {
                    $sheet->getStyle("B{$currentRow}:M{$currentRow}")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ]);
                }

                $currentRow++;
                $no++;
            }
        }

        // Total Row
        $sheet->mergeCells("B{$currentRow}:K{$currentRow}");
        $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN BIAYA TRANSPORTASI');
        $sheet->setCellValue("L{$currentRow}", "=SUM(L{$headerRow}:L" . ($currentRow - 1) . ")");
        $sheet->setCellValue("M{$currentRow}", "-");

        $sheet->getStyle("L{$currentRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');

        $totalRange = "B{$currentRow}:M{$currentRow}";
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        $tableRange = "B{$headerRow}:M{$currentRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        foreach (range('B', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Rekap_Biaya_Transportasi_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Full User Budget & Expense Recap to Excel (.xlsx)
     */
    public function exportUserBudgetRecapExcel(iterable $employees, ?int $month = null, ?int $year = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Budget Karyawan');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(3);

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = ($month && isset($monthNames[$month]) ? $monthNames[$month] : 'Semua Periode') . ($year ? " {$year}" : '');

        // Header Title
        $sheet->mergeCells('B1:U1');
        $sheet->setCellValue('B1', 'REKAPAN BUDGET & SISA SALDO KARYAWAN — PT MEDIA SELULAR INDONESIA');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B2:U2');
        $sheet->setCellValue('B2', 'Periode: ' . $periodLabel . ' | Tanggal Cetak: ' . date('d F Y H:i'));
        $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB('64748B');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header Structure (Double row header)
        // Row 4: Category Group Headers
        $sheet->mergeCells('B4:B5'); $sheet->setCellValue('B4', 'NO');
        $sheet->mergeCells('C4:C5'); $sheet->setCellValue('C4', 'NAMA KARYAWAN');
        $sheet->mergeCells('D4:D5'); $sheet->setCellValue('D4', 'JABATAN / ROLE');
        $sheet->mergeCells('E4:E5'); $sheet->setCellValue('E4', 'AREA / KOTA');

        $sheet->mergeCells('F4:H4'); $sheet->setCellValue('F4', 'BIAYA BBM');
        $sheet->setCellValue('F5', 'PLAFON');
        $sheet->setCellValue('G5', 'TERPAKAI');
        $sheet->setCellValue('H5', 'SISA');

        $sheet->mergeCells('I4:K4'); $sheet->setCellValue('I4', 'BIAYA ENTERTAIN');
        $sheet->setCellValue('I5', 'PLAFON');
        $sheet->setCellValue('J5', 'TERPAKAI');
        $sheet->setCellValue('K5', 'SISA');

        $sheet->mergeCells('L4:N4'); $sheet->setCellValue('L4', 'PERJALANAN DINAS');
        $sheet->setCellValue('L5', 'PLAFON');
        $sheet->setCellValue('M5', 'TERPAKAI');
        $sheet->setCellValue('N5', 'SISA');

        $sheet->mergeCells('O4:Q4'); $sheet->setCellValue('O4', 'SERVICE & TRANSPORT');
        $sheet->setCellValue('O5', 'PLAFON');
        $sheet->setCellValue('P5', 'TERPAKAI');
        $sheet->setCellValue('Q5', 'SISA');

        $sheet->mergeCells('R4:T4'); $sheet->setCellValue('R4', 'TOTAL KESELURUHAN');
        $sheet->setCellValue('R5', 'TOTAL PLAFON');
        $sheet->setCellValue('S5', 'TOTAL TERPAKAI');
        $sheet->setCellValue('T5', 'TOTAL SISA SALDO');

        $sheet->mergeCells('U4:U5'); $sheet->setCellValue('U4', 'STATUS SALDO');

        $sheet->getStyle('B4:U5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);
        $sheet->getRowDimension(5)->setRowHeight(20);

        $currentRow = 6;
        $no = 1;

        foreach ($employees as $emp) {
            $bbmBudget = (float)$emp->bbm_budget;
            $bbmUsed = (float)$emp->getUsedBbmForPeriod($month, $year);
            $bbmRemaining = $bbmBudget - $bbmUsed;

            $entBudget = (float)$emp->entertain_budget;
            $entUsed = (float)$emp->getUsedEntertainForPeriod($month, $year, true);
            $entRemaining = $entBudget - $entUsed;

            $perdinBudget = (float)$emp->perdin_budget;
            $perdinUsed = (float)$emp->getUsedPerdinForPeriod($month, $year);
            $perdinRemaining = $perdinBudget - $perdinUsed;

            $transBudget = (float)$emp->service_motor_budget;
            $transUsed = (float)$emp->getUsedTransportForPeriod($month, $year);
            $transRemaining = $transBudget - $transUsed;

            $totalBudget = (float)$emp->total_budget;
            $totalUsed = (float)$emp->getTotalExpenseUsedForPeriod($month, $year);
            $totalRemaining = $totalBudget - $totalUsed;

            $isOver = ($bbmBudget > 0 && $bbmUsed > $bbmBudget) ||
                      ($entBudget > 0 && $entUsed > $entBudget) ||
                      ($perdinBudget > 0 && $perdinUsed > $perdinBudget) ||
                      ($transBudget > 0 && $transUsed > $transBudget) ||
                      ($totalBudget > 0 && $totalUsed > $totalBudget);

            $statusText = $isOver ? '⚠️ OVER BUDGET' : '✅ AMAN';

            $sheet->setCellValue("B{$currentRow}", $no);
            $sheet->setCellValue("C{$currentRow}", $emp->name);
            $sheet->setCellValue("D{$currentRow}", $emp->position_name ?? ($emp->role?->name ?? 'Sales'));
            $sheet->setCellValue("E{$currentRow}", $emp->homebase ?? '-');

            $sheet->setCellValue("F{$currentRow}", $bbmBudget);
            $sheet->setCellValue("G{$currentRow}", $bbmUsed);
            $sheet->setCellValue("H{$currentRow}", $bbmRemaining);

            $sheet->setCellValue("I{$currentRow}", $entBudget);
            $sheet->setCellValue("J{$currentRow}", $entUsed);
            $sheet->setCellValue("K{$currentRow}", $entRemaining);

            $sheet->setCellValue("L{$currentRow}", $perdinBudget);
            $sheet->setCellValue("M{$currentRow}", $perdinUsed);
            $sheet->setCellValue("N{$currentRow}", $perdinRemaining);

            $sheet->setCellValue("O{$currentRow}", $transBudget);
            $sheet->setCellValue("P{$currentRow}", $transUsed);
            $sheet->setCellValue("Q{$currentRow}", $transRemaining);

            $sheet->setCellValue("R{$currentRow}", $totalBudget);
            $sheet->setCellValue("S{$currentRow}", $totalUsed);
            $sheet->setCellValue("T{$currentRow}", $totalRemaining);

            $sheet->setCellValue("U{$currentRow}", $statusText);

            // Alignments & formats
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getFont()->setBold(true);
            $sheet->getStyle("U{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("U{$currentRow}")->getFont()->setBold(true)->getColor()->setRGB($isOver ? 'DC2626' : '166534');

            // Currency formats
            foreach (range('F', 'T') as $col) {
                $sheet->getStyle("{$col}{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
            }

            if ($no % 2 === 0) {
                $sheet->getStyle("B{$currentRow}:U{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $currentRow++;
            $no++;
        }

        // Summary Total Row
        $sheet->mergeCells("B{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN');
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        foreach (range('F', 'T') as $col) {
            $sheet->setCellValue("{$col}{$currentRow}", "=SUM({$col}6:{$col}" . ($currentRow - 1) . ")");
            $sheet->getStyle("{$col}{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        $sheet->setCellValue("U{$currentRow}", "-");
        $sheet->getStyle("U{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $totalRange = "B{$currentRow}:U{$currentRow}";
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9.5],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($currentRow)->setRowHeight(24);

        $tableRange = "B4:U{$currentRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        foreach (range('B', 'U') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Rekapan_Budget_Dan_Sisa_Saldo_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export BBM transactions for a single employee (Monthly or All-Time)
     */
    public function exportUserBbmTransactions(Employee $employee, ?int $month = null, ?int $year = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap BBM User');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(3);

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = ($month && isset($monthNames[$month]) ? $monthNames[$month] : 'Semua Transaksi (All-Time)') . ($year ? " {$year}" : '');

        // Header Title
        $sheet->mergeCells('B1:M1');
        $sheet->setCellValue('B1', 'REKAPITULASI BIAYA PENGISIAN BBM KARYAWAN');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B2:M2');
        $sheet->setCellValue('B2', "Karyawan: {$employee->name} ({$employee->position_name}) | Area: " . ($employee->region ?: $employee->homebase ?: '-') . " | Periode: {$periodLabel}");
        $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB('475569');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Budget summary section
        $budget = (float)$employee->bbm_budget;
        $used = (float)$employee->getUsedBbmForPeriod($month, $year);
        $diff = $budget - $used;

        $sheet->setCellValue('B4', 'Plafon BBM:');
        $sheet->setCellValue('C4', $budget > 0 ? "Rp " . number_format($budget, 0, ',', '.') : "Tanpa Plafon");
        $sheet->setCellValue('E4', 'Total Terpakai:');
        $sheet->setCellValue('F4', "Rp " . number_format($used, 0, ',', '.'));
        $sheet->setCellValue('H4', 'Sisa Saldo:');
        $sheet->setCellValue('I4', $budget > 0 ? ($diff < 0 ? "-Rp " : "Rp ") . number_format(abs($diff), 0, ',', '.') : "-");
        $sheet->setCellValue('K4', 'Status:');
        $sheet->setCellValue('L4', $budget > 0 ? ($diff < 0 ? "⚠️ LEWAT BATAS" : "✅ AMAN") : "NORMAL");

        $sheet->getStyle('B4:L4')->getFont()->setBold(true);
        $sheet->getStyle('B4:L4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');

        $headerRow = 6;
        $headers = [
            'B' => 'NO',
            'C' => 'TGL PENGAJUAN',
            'D' => 'TGL NOTA BBM',
            'E' => '_UID',
            'F' => 'SPBU / LOKASI',
            'G' => 'KENDARAAN',
            'H' => 'KM AWAL',
            'I' => 'JENIS BBM',
            'J' => 'VOLUME (LITER)',
            'K' => 'NOTA SPBU (RP)',
            'L' => 'TOTAL KLAIM (RP)',
            'M' => 'STATUS PENCAIRAN',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue("{$col}{$headerRow}", $text);
        }

        $headerRange = "B{$headerRow}:M{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9.5],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        $claims = $employee->getBbmClaims($month, $year);
        $currentRow = $headerRow + 1;
        $no = 1;
        $totalNota = 0;
        $totalKlaim = 0;

        foreach ($claims as $claim) {
            $notaDate = $claim->fuel_receipt_date ? $claim->fuel_receipt_date->format('d/m/Y') : ($claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-');
            $claimDateStr = $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-';
            $baseAmount = (float)($claim->fuel_base_amount > 0 ? $claim->fuel_base_amount : $claim->amount);
            $amount = (float)$claim->amount;
            $kmAwal = $claim->fuel_start_km > 0 ? number_format($claim->fuel_start_km, 0, ',', '.') . ' KM' : '-';

            $totalNota += $baseAmount;
            $totalKlaim += $amount;

            $sheet->setCellValue("B{$currentRow}", $no);
            $sheet->setCellValue("C{$currentRow}", $claimDateStr);
            $sheet->setCellValue("D{$currentRow}", $notaDate);
            $sheet->setCellValue("E{$currentRow}", $claim->_uid ?: '-');
            $sheet->setCellValue("F{$currentRow}", $claim->note ?: ($claim->city ?: '-'));
            $sheet->setCellValue("G{$currentRow}", $claim->vehicle_type ?: '-');
            $sheet->setCellValue("H{$currentRow}", $kmAwal);
            $sheet->setCellValue("I{$currentRow}", $claim->fuel_type ?: '-');
            $sheet->setCellValue("J{$currentRow}", $claim->fuel_liters > 0 ? $claim->fuel_liters : '-');
            $sheet->setCellValue("K{$currentRow}", $baseAmount);
            $sheet->setCellValue("L{$currentRow}", $amount);
            $sheet->setCellValue("M{$currentRow}", $claim->disbursement_status ?: 'Belum Dicairkan');

            $sheet->getStyle("K{$currentRow}:L{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

            if ($no % 2 === 0) {
                $sheet->getStyle("B{$currentRow}:M{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $currentRow++;
            $no++;
        }

        if ($claims->isEmpty()) {
            $sheet->mergeCells("B{$currentRow}:M{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", 'Tidak ada transaksi klaim BBM pada periode ini.');
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
            $currentRow++;
        } else {
            $sheet->mergeCells("B{$currentRow}:J{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN:');
            $sheet->setCellValue("K{$currentRow}", $totalNota);
            $sheet->setCellValue("L{$currentRow}", $totalKlaim);
            $sheet->setCellValue("M{$currentRow}", '');

            $sheet->getStyle("B{$currentRow}:M{$currentRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ]);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("K{$currentRow}:L{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
            $currentRow++;
        }

        $lastRow = $currentRow - 1;
        $sheet->getStyle("B{$headerRow}:M{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        foreach (range('B', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $employee->name);
        $periodSuffix = $month ? "Bulan_{$month}_{$year}" : ($year ? "Tahun_{$year}" : "AllTime");
        $filename = "Rekap_BBM_{$cleanName}_{$periodSuffix}_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Entertain transactions for a single employee (Monthly or All-Time)
     */
    public function exportUserEntertainTransactions(Employee $employee, ?int $month = null, ?int $year = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Entertain User');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(3);

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = ($month && isset($monthNames[$month]) ? $monthNames[$month] : 'Semua Transaksi (All-Time)') . ($year ? " {$year}" : '');

        // Header Title
        $sheet->mergeCells('B1:J1');
        $sheet->setCellValue('B1', 'REKAPITULASI BIAYA ENTERTAIN & TRANSPORT KARYAWAN');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B2:J2');
        $sheet->setCellValue('B2', "Karyawan: {$employee->name} ({$employee->position_name}) | Area: " . ($employee->region ?: $employee->homebase ?: '-') . " | Periode: {$periodLabel}");
        $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB('475569');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Budget summary section
        $budget = (float)$employee->entertain_budget;
        $used = (float)$employee->getUsedEntertainForPeriod($month, $year, true);
        $diff = $budget - $used;

        $sheet->setCellValue('B4', 'Plafon Entertain:');
        $sheet->setCellValue('C4', $budget > 0 ? "Rp " . number_format($budget, 0, ',', '.') : "Tanpa Plafon");
        $sheet->setCellValue('D4', 'Total Terpakai:');
        $sheet->setCellValue('E4', "Rp " . number_format($used, 0, ',', '.'));
        $sheet->setCellValue('F4', 'Sisa Saldo:');
        $sheet->setCellValue('G4', $budget > 0 ? ($diff < 0 ? "-Rp " : "Rp ") . number_format(abs($diff), 0, ',', '.') : "-");
        $sheet->setCellValue('H4', 'Status:');
        $sheet->setCellValue('I4', $budget > 0 ? ($diff < 0 ? "⚠️ LEWAT BATAS" : "✅ AMAN") : "NORMAL");

        $sheet->getStyle('B4:I4')->getFont()->setBold(true);
        $sheet->getStyle('B4:I4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');

        $headerRow = 6;
        $headers = [
            'B' => 'NO',
            'C' => 'TGL PENGAJUAN',
            'D' => '_UID',
            'E' => 'JENIS BIAYA',
            'F' => 'AGENDA / KEPERLUAN',
            'G' => 'LOKASI / TEMPAT',
            'H' => 'KOTA',
            'I' => 'NOMINAL (RP)',
            'J' => 'STATUS PENCAIRAN',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue("{$col}{$headerRow}", $text);
        }

        $headerRange = "B{$headerRow}:J{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9.5],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        $claims = $employee->getEntertainClaims($month, $year);
        $currentRow = $headerRow + 1;
        $no = 1;
        $totalNominal = 0;

        foreach ($claims as $claim) {
            $claimDateStr = $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-';
            $amount = (float)$claim->amount;
            $totalNominal += $amount;

            $sheet->setCellValue("B{$currentRow}", $no);
            $sheet->setCellValue("C{$currentRow}", $claimDateStr);
            $sheet->setCellValue("D{$currentRow}", $claim->_uid ?: '-');
            $sheet->setCellValue("E{$currentRow}", $claim->claim_type_string ?: '-');
            $sheet->setCellValue("F{$currentRow}", $claim->purpose_string ?: '-');
            $sheet->setCellValue("G{$currentRow}", $claim->note ?: '-');
            $sheet->setCellValue("H{$currentRow}", $claim->city ?: '-');
            $sheet->setCellValue("I{$currentRow}", $amount);
            $sheet->setCellValue("J{$currentRow}", $claim->disbursement_status ?: 'Belum Dicairkan');

            $sheet->getStyle("I{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

            if ($no % 2 === 0) {
                $sheet->getStyle("B{$currentRow}:J{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $currentRow++;
            $no++;
        }

        if ($claims->isEmpty()) {
            $sheet->mergeCells("B{$currentRow}:J{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", 'Tidak ada transaksi klaim Entertain pada periode ini.');
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
            $currentRow++;
        } else {
            $sheet->mergeCells("B{$currentRow}:H{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN:');
            $sheet->setCellValue("I{$currentRow}", $totalNominal);
            $sheet->setCellValue("J{$currentRow}", '');

            $sheet->getStyle("B{$currentRow}:J{$currentRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ]);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
            $currentRow++;
        }

        $lastRow = $currentRow - 1;
        $sheet->getStyle("B{$headerRow}:J{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        foreach (range('B', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $employee->name);
        $periodSuffix = $month ? "Bulan_{$month}_{$year}" : ($year ? "Tahun_{$year}" : "AllTime");
        $filename = "Rekap_Entertain_{$cleanName}_{$periodSuffix}_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export all users BBM summary recap table to Excel
     */
    public function exportBbmUsersSummaryRecap(iterable $employees, ?int $month = null, ?int $year = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap BBM Karyawan');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(3);

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = ($month && isset($monthNames[$month]) ? $monthNames[$month] : 'Semua Periode') . ($year ? " {$year}" : '');

        // Header Title
        $sheet->mergeCells('B1:J1');
        $sheet->setCellValue('B1', 'REKAPITULASI BIAYA BBM KARYAWAN — PT MEDIA SELULAR INDONESIA');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B2:J2');
        $sheet->setCellValue('B2', 'Periode: ' . $periodLabel . ' | Tanggal Cetak: ' . date('d F Y H:i'));
        $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB('64748B');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 4;
        $headers = [
            'B' => 'NO',
            'C' => 'NAMA KARYAWAN',
            'D' => 'JABATAN',
            'E' => 'AREA / REGION',
            'F' => 'PLAFON BBM (RP)',
            'G' => 'TERPAKAI (RP)',
            'H' => 'SISA SALDO (RP)',
            'I' => 'STATUS PLAFON',
            'J' => 'JML TRANSAKSI',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue("{$col}{$headerRow}", $text);
        }

        $headerRange = "B{$headerRow}:J{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9.5],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        $currentRow = $headerRow + 1;
        $no = 1;
        $totPlafon = 0;
        $totPakai = 0;
        $totDiff = 0;
        $totTrx = 0;

        foreach ($employees as $emp) {
            $budget = (float)$emp->bbm_budget;
            $used = (float)$emp->getUsedBbmForPeriod($month, $year);
            $diff = $budget - $used;
            $trxCount = $emp->getBbmClaimsCountForPeriod($month, $year);

            $totPlafon += $budget;
            $totPakai += $used;
            $totDiff += $diff;
            $totTrx += $trxCount;

            $statusText = $budget > 0 ? ($diff < 0 ? '⚠️ Over Plafon' : '✅ Aman') : '-';

            $sheet->setCellValue("B{$currentRow}", $no);
            $sheet->setCellValue("C{$currentRow}", $emp->name);
            $sheet->setCellValue("D{$currentRow}", $emp->position_name);
            $sheet->setCellValue("E{$currentRow}", $emp->region ?: ($emp->homebase ?: '-'));
            $sheet->setCellValue("F{$currentRow}", $budget);
            $sheet->setCellValue("G{$currentRow}", $used);
            $sheet->setCellValue("H{$currentRow}", $budget > 0 ? $diff : '-');
            $sheet->setCellValue("I{$currentRow}", $statusText);
            $sheet->setCellValue("J{$currentRow}", $trxCount);

            $sheet->getStyle("F{$currentRow}:G{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
            if ($budget > 0) {
                $sheet->getStyle("H{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
            }

            if ($no % 2 === 0) {
                $sheet->getStyle("B{$currentRow}:J{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $currentRow++;
            $no++;
        }

        // Summary Total Row
        $sheet->mergeCells("B{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN:');
        $sheet->setCellValue("F{$currentRow}", $totPlafon);
        $sheet->setCellValue("G{$currentRow}", $totPakai);
        $sheet->setCellValue("H{$currentRow}", $totDiff);
        $sheet->setCellValue("I{$currentRow}", '');
        $sheet->setCellValue("J{$currentRow}", $totTrx);

        $sheet->getStyle("B{$currentRow}:J{$currentRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("F{$currentRow}:H{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

        $lastRow = $currentRow;
        $sheet->getStyle("B{$headerRow}:J{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        foreach (range('B', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Rekap_BBM_Semua_Karyawan_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export all users Entertain summary recap table to Excel
     */
    public function exportEntertainUsersSummaryRecap(iterable $employees, ?int $month = null, ?int $year = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Entertain Karyawan');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getColumnDimension('A')->setWidth(3);

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = ($month && isset($monthNames[$month]) ? $monthNames[$month] : 'Semua Periode') . ($year ? " {$year}" : '');

        // Header Title
        $sheet->mergeCells('B1:J1');
        $sheet->setCellValue('B1', 'REKAPITULASI BIAYA ENTERTAIN KARYAWAN — PT MEDIA SELULAR INDONESIA');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B2:J2');
        $sheet->setCellValue('B2', 'Periode: ' . $periodLabel . ' | Tanggal Cetak: ' . date('d F Y H:i'));
        $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB('64748B');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 4;
        $headers = [
            'B' => 'NO',
            'C' => 'NAMA KARYAWAN',
            'D' => 'JABATAN',
            'E' => 'AREA / REGION',
            'F' => 'PLAFON ENTERTAIN (RP)',
            'G' => 'TERPAKAI (RP)',
            'H' => 'SISA SALDO (RP)',
            'I' => 'STATUS PLAFON',
            'J' => 'JML TRANSAKSI',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue("{$col}{$headerRow}", $text);
        }

        $headerRange = "B{$headerRow}:J{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9.5],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        $currentRow = $headerRow + 1;
        $no = 1;
        $totPlafon = 0;
        $totPakai = 0;
        $totDiff = 0;
        $totTrx = 0;

        foreach ($employees as $emp) {
            $budget = (float)$emp->entertain_budget;
            $used = (float)$emp->getUsedEntertainForPeriod($month, $year, true);
            $diff = $budget - $used;
            $trxCount = $emp->getEntertainClaimsCountForPeriod($month, $year);

            $totPlafon += $budget;
            $totPakai += $used;
            $totDiff += $diff;
            $totTrx += $trxCount;

            $statusText = $budget > 0 ? ($diff < 0 ? '⚠️ Over Plafon' : '✅ Aman') : '-';

            $sheet->setCellValue("B{$currentRow}", $no);
            $sheet->setCellValue("C{$currentRow}", $emp->name);
            $sheet->setCellValue("D{$currentRow}", $emp->position_name);
            $sheet->setCellValue("E{$currentRow}", $emp->region ?: ($emp->homebase ?: '-'));
            $sheet->setCellValue("F{$currentRow}", $budget);
            $sheet->setCellValue("G{$currentRow}", $used);
            $sheet->setCellValue("H{$currentRow}", $budget > 0 ? $diff : '-');
            $sheet->setCellValue("I{$currentRow}", $statusText);
            $sheet->setCellValue("J{$currentRow}", $trxCount);

            $sheet->getStyle("F{$currentRow}:G{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
            if ($budget > 0) {
                $sheet->getStyle("H{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
            }

            if ($no % 2 === 0) {
                $sheet->getStyle("B{$currentRow}:J{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $currentRow++;
            $no++;
        }

        // Summary Total Row
        $sheet->mergeCells("B{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("B{$currentRow}", 'TOTAL KESELURUHAN:');
        $sheet->setCellValue("F{$currentRow}", $totPlafon);
        $sheet->setCellValue("G{$currentRow}", $totPakai);
        $sheet->setCellValue("H{$currentRow}", $totDiff);
        $sheet->setCellValue("I{$currentRow}", '');
        $sheet->setCellValue("J{$currentRow}", $totTrx);

        $sheet->getStyle("B{$currentRow}:J{$currentRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
        ]);
        $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("F{$currentRow}:H{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

        $lastRow = $currentRow;
        $sheet->getStyle("B{$headerRow}:J{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        foreach (range('B', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Rekap_Entertain_Semua_Karyawan_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Embed all photos for a claim (BBM before/after pair composites, individual odometer photos, receipts/nota, return transfer proofs, and finance transfer proofs)
     */
    public function embedClaimPhotosToWorksheet(Worksheet $sheet, Claim $claim, int $startRow, bool $includeTitle = true): int
    {
        $currentRow = $startRow;
        $bbmPairs = $claim->getBbmPairCombinedPhotos();
        $items = is_array($claim->items) ? $claim->items : [];

        // 1. General Photos (nota fisik / struk)
        $generalPhotos = [];
        if (is_array($claim->photos)) {
            foreach ($claim->photos as $p) {
                if ($p && Storage::disk('public')->exists($p)) {
                    $generalPhotos[] = Storage::disk('public')->path($p);
                }
            }
        }

        // 2. Return Transfer Proof
        $returnProofPath = null;
        if ($claim->return_transfer_proof && Storage::disk('public')->exists($claim->return_transfer_proof)) {
            $returnProofPath = Storage::disk('public')->path($claim->return_transfer_proof);
        }

        // 3. Finance Transfer Proof
        $financeProofPath = null;
        if ($claim->transfer_proof_photo && Storage::disk('public')->exists($claim->transfer_proof_photo)) {
            $financeProofPath = Storage::disk('public')->path($claim->transfer_proof_photo);
        }

        $hasAnyPhoto = !empty($bbmPairs) || !empty($generalPhotos) || !empty($returnProofPath) || !empty($financeProofPath) || !empty($items) || !empty($claim->bbm_photo_before) || !empty($claim->bbm_photo_after) || !empty($claim->bbm_photo_combined);
        if (!$hasAnyPhoto) {
            return $currentRow;
        }

        // SECTION A: FOTO DOKUMENTASI BBM (BEFORE & AFTER ODOMETER PER TRANSAKSI)
        if (!empty($bbmPairs) || count($items) > 0 || $claim->bbm_photo_before || $claim->bbm_photo_after) {
            if ($includeTitle) {
                $sheet->setCellValue("B{$currentRow}", 'LAMPIRAN FOTO DOKUMENTASI BBM (BEFORE & AFTER ODOMETER PER TRANSAKSI):');
                $sheet->getStyle("B{$currentRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('1E293B');
                $currentRow += 2;
            }

            if (!empty($bbmPairs)) {
                foreach ($bbmPairs as $idx => $pair) {
                    $sheet->setCellValue("B{$currentRow}", $pair['label']);
                    $sheet->getStyle("B{$currentRow}")->getFont()->setBold(true)->setSize(9.5)->getColor()->setRGB('0F766E');
                    $currentRow++;

                    $combinedPath = $pair['combined_path'] ?? null;
                    if (!empty($combinedPath) && file_exists($combinedPath)) {
                        try {
                            $drawing = new Drawing();
                            $drawing->setName($pair['label']);
                            $drawing->setDescription($pair['label']);
                            $drawing->setPath($combinedPath);
                            $drawing->setHeight(220);
                            $drawing->setCoordinates("B{$currentRow}");
                            $drawing->setWorksheet($sheet);
                            $currentRow += 13;
                        } catch (\Throwable $e) {
                            $currentRow += 2;
                        }
                    } else {
                        // Fallback to separate before / after
                        $before = $pair['before_path'] ?? null;
                        $after = $pair['after_path'] ?? null;
                        if ($before && file_exists($before)) {
                            try {
                                $dB = new Drawing();
                                $dB->setName("Before #" . ($idx + 1));
                                $dB->setPath($before);
                                $dB->setHeight(200);
                                $dB->setCoordinates("B{$currentRow}");
                                $dB->setWorksheet($sheet);
                            } catch (\Throwable $e) {}
                        }
                        if ($after && file_exists($after)) {
                            try {
                                $dA = new Drawing();
                                $dA->setName("After #" . ($idx + 1));
                                $dA->setPath($after);
                                $dA->setHeight(200);
                                $dA->setCoordinates("F{$currentRow}");
                                $dA->setWorksheet($sheet);
                            } catch (\Throwable $e) {}
                        }
                        if (($before && file_exists($before)) || ($after && file_exists($after))) {
                            $currentRow += 13;
                        }
                    }
                }
            }
            $currentRow++;
        }

        // SECTION B: FOTO NOTA FISIK / STRUK PEMBELIAN
        if (!empty($generalPhotos)) {
            $sheet->setCellValue("B{$currentRow}", 'LAMPIRAN FOTO NOTA FISIK / STRUK / DOKUMEN PENDUKUNG:');
            $sheet->getStyle("B{$currentRow}")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('1E293B');
            $currentRow += 2;

            $colPositions = ['B', 'F'];
            $gIdx = 0;
            $baseRow = $currentRow;

            foreach ($generalPhotos as $gPath) {
                if (file_exists($gPath)) {
                    try {
                        $targetCol = $colPositions[$gIdx % 2];
                        $sheet->setCellValue("{$targetCol}{$baseRow}", 'Foto Nota / Struk #' . ($gIdx + 1));
                        $sheet->getStyle("{$targetCol}{$baseRow}")->getFont()->setItalic(true)->setSize(9);

                        $drawingGen = new Drawing();
                        $drawingGen->setName('Foto Nota ' . ($gIdx + 1));
                        $drawingGen->setPath($gPath);
                        $drawingGen->setHeight(190);
                        $drawingGen->setCoordinates("{$targetCol}" . ($baseRow + 1));
                        $drawingGen->setWorksheet($sheet);

                        if ($gIdx % 2 === 1) {
                            $baseRow += 12;
                        }
                        $gIdx++;
                    } catch (\Throwable $e) {}
                }
            }
            if ($gIdx % 2 === 1) {
                $baseRow += 12;
            }
            $currentRow = $baseRow + 1;
        }

        // SECTION C: BUKTI TRANSFER PENGEMBALIAN DANA (TRANSFER BALIK KE FINANCE)
        if ($returnProofPath && file_exists($returnProofPath)) {
            $sheet->setCellValue("B{$currentRow}", 'LAMPIRAN BUKTI TRANSFER PENGEMBALIAN DANA KE FINANCE:');
            $sheet->getStyle("B{$currentRow}")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('0F766E');
            $currentRow++;

            $retAmountStr = $claim->return_transfer_amount ? 'Rp ' . number_format((float)$claim->return_transfer_amount, 0, ',', '.') : '-';
            $retDateStr = $claim->return_transferred_at ? date('d/m/Y H:i', strtotime($claim->return_transferred_at)) : '-';
            $retNotes = $claim->return_transfer_notes ?: '-';

            $sheet->setCellValue("B{$currentRow}", "Nominal Pengembalian: {$retAmountStr} | Tanggal Transfer: {$retDateStr} | Catatan: {$retNotes}");
            $sheet->getStyle("B{$currentRow}")->getFont()->setSize(9)->setItalic(true);
            $currentRow++;

            try {
                $drawingRet = new Drawing();
                $drawingRet->setName('Bukti Transfer Pengembalian');
                $drawingRet->setPath($returnProofPath);
                $drawingRet->setHeight(340);
                $drawingRet->setCoordinates("B{$currentRow}");
                $drawingRet->setWorksheet($sheet);
                $currentRow += 19;
            } catch (\Throwable $e) {
                $currentRow += 2;
            }
        }

        // SECTION D: BUKTI PENCAIRAN OLEH FINANCE
        if ($financeProofPath && file_exists($financeProofPath)) {
            $sheet->setCellValue("B{$currentRow}", 'LAMPIRAN BUKTI PENCAIRAN DANA OLEH FINANCE:');
            $sheet->getStyle("B{$currentRow}")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('1E293B');
            $currentRow++;

            $disbDateStr = $claim->disbursed_at ? date('d/m/Y H:i', strtotime($claim->disbursed_at)) : '-';
            $sheet->setCellValue("B{$currentRow}", "Status: {$claim->disbursement_status} | Tanggal Pencairan: {$disbDateStr}");
            $sheet->getStyle("B{$currentRow}")->getFont()->setSize(9)->setItalic(true);
            $currentRow++;

            try {
                $drawingFin = new Drawing();
                $drawingFin->setName('Bukti Pencairan Finance');
                $drawingFin->setPath($financeProofPath);
                $drawingFin->setHeight(340);
                $drawingFin->setCoordinates("B{$currentRow}");
                $drawingFin->setWorksheet($sheet);
                $currentRow += 19;
            } catch (\Throwable $e) {
                $currentRow += 2;
            }
        }

        return $currentRow;
    }
}


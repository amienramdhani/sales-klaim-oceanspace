<?php

namespace App\Services;

use App\Models\Claim;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BbmClaimWordExportService
{
    /**
     * Export Klaim BBM to Microsoft Word (.docx) document
     */
    public function export(Claim $claim, array $customData = []): BinaryFileResponse
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(9.5);

        $emp = $claim->effective_employee;
        $empName = $customData['name'] ?? ($emp?->name ?? 'Karyawan');
        $empNik = $emp?->nik ?? '-';
        $empPosition = $customData['position'] ?? ($emp?->position_name ?? 'ASM');
        $area = $claim->city ?: ($claim->branch?->name ?? 'Purwokerto / Cirebon');
        $claimDate = $claim->claim_date ? $claim->claim_date->translatedFormat('d F Y') : date('d F Y');
        $uid = $claim->_uid ?: '-';
        $bbmBudget = (float)($emp?->bbm_budget ?? ($emp?->positionModel?->bbm_budget ?? 0));

        // SECTION 1: HALAMAN UTAMA FORM KLAIM BBM
        $section = $phpWord->addSection([
            'marginTop' => 650,
            'marginBottom' => 650,
            'marginLeft' => 850,
            'marginRight' => 850,
        ]);

        $this->addHeaderKop($section);

        $section->addTextBreak(1);

        // Title
        $section->addText('BIAYA OPERASIONAL BBM', ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $section->addText('PT. MEDIA SELULAR INDONESIA', ['bold' => true, 'size' => 10, 'color' => '475569'], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);

        // Box Informasi Pemohon & Plafon
        $infoTable = $section->addTable([
            'alignment' => JcTable::CENTER,
            'unit' => TblWidth::PERCENT,
            'width' => 100 * 50,
            'borders' => [
                'top' => ['borderStyle' => 'thin', 'color' => 'CBD5E1'],
                'bottom' => ['borderStyle' => 'thin', 'color' => 'CBD5E1'],
                'left' => ['borderStyle' => 'thin', 'color' => 'CBD5E1'],
                'right' => ['borderStyle' => 'thin', 'color' => 'CBD5E1'],
            ],
        ]);

        $infoTable->addRow(260);
        $c1 = $infoTable->addCell(4500, ['bgColor' => 'F8FAFC']);
        $c1->addText("Nama Pemohon    : {$empName}", ['bold' => true, 'size' => 9], ['spaceAfter' => 20]);
        $c1->addText("Jabatan / Role     : {$empPosition}", ['size' => 9], ['spaceAfter' => 20]);
        $c1->addText("Cabang / Area    : {$area}", ['size' => 9], ['spaceAfter' => 20]);

        $c2 = $infoTable->addCell(4500, ['bgColor' => 'F8FAFC']);
        $c2->addText("Nomor _UID        : {$uid}", ['bold' => true, 'size' => 9, 'color' => '0F766E'], ['spaceAfter' => 20]);
        $c2->addText("Tanggal Klaim   : {$claimDate}", ['size' => 9], ['spaceAfter' => 20]);
        $budgetStr = $bbmBudget > 0 ? "Rp " . number_format($bbmBudget, 0, ',', '.') : "-";
        $c2->addText("Budget BBM Awal: {$budgetStr}", ['bold' => true, 'size' => 9, 'color' => '1E293B'], ['spaceAfter' => 20]);

        $section->addTextBreak(1);

        // Section Title
        $section->addText('RINCIAN TRANSAKSI PENGISIAN BBM :', ['bold' => true, 'size' => 9.5], ['spaceAfter' => 40]);

        // Table Rincian Transaksi: NO | TGL NOTA | REFFNOTE | KENDARAAN | KM AWAL | NOTA (Rp) | DISETUJUI (Rp)
        $detailTable = $section->addTable([
            'alignment' => JcTable::CENTER,
            'unit' => TblWidth::PERCENT,
            'width' => 100 * 50,
            'borders' => [
                'allBorders' => ['borderStyle' => 'thin', 'color' => '94A3B8'],
            ],
        ]);

        // Header Row
        $detailTable->addRow(320);
        $thStyle = ['bgColor' => '1E293B'];
        $thFont = ['bold' => true, 'color' => 'FFFFFF', 'size' => 8.5];
        $thAlign = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];

        $detailTable->addCell(400, $thStyle)->addText('NO', $thFont, $thAlign);
        $detailTable->addCell(1200, $thStyle)->addText('TGL NOTA', $thFont, $thAlign);
        $detailTable->addCell(2600, $thStyle)->addText('REFFNOTE', $thFont, $thAlign);
        $detailTable->addCell(1100, $thStyle)->addText('KENDARAAN', $thFont, $thAlign);
        $detailTable->addCell(1100, $thStyle)->addText('KM AWAL', $thFont, $thAlign);
        $detailTable->addCell(1200, $thStyle)->addText('NOTA (Rp)', $thFont, $thAlign);
        $detailTable->addCell(1400, $thStyle)->addText('DISETUJUI (Rp)', $thFont, $thAlign);

        $lineItems = $claim->getLineItems();
        $no = 1;
        $totalNota = 0;
        $totalDisetujui = 0;

        foreach ($lineItems as $item) {
            $tglNota = !empty($item['receipt_date']) ? date('d/m/Y', strtotime($item['receipt_date'])) : ($claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-');
            $reffnote = !empty($item['reffnote']) ? $item['reffnote'] : ($claim->reffnote ?: ($claim->branch?->reffnote ?: ($claim->branch?->name ?? ($item['note'] ?? '-'))));
            $veh = $item['vehicle_type'] ?? ($claim->vehicle_type ?? 'Mobil');
            $km = !empty($item['fuel_start_km']) ? number_format((float)$item['fuel_start_km'], 0, ',', '.') . ' KM' : ($claim->fuel_start_km ? number_format((float)$claim->fuel_start_km, 0, ',', '.') . ' KM' : '-');
            $base = (float)($item['fuel_base_amount'] ?? ($claim->fuel_base_amount ?? ($item['amount'] ?? 0)));
            $amountApproved = (float)($item['amount'] ?? $claim->amount);

            $totalNota += $base;
            $totalDisetujui += $amountApproved;

            $bg = ($no % 2 === 0) ? ['bgColor' => 'F8FAFC'] : [];

            $detailTable->addRow(280);
            $detailTable->addCell(400, $bg)->addText($no, ['size' => 8.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $detailTable->addCell(1200, $bg)->addText($tglNota, ['size' => 8.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $detailTable->addCell(2600, $bg)->addText($reffnote, ['size' => 8.5], ['spaceAfter' => 0]);
            $detailTable->addCell(1100, $bg)->addText($veh, ['size' => 8.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $detailTable->addCell(1100, $bg)->addText($km, ['size' => 8.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $detailTable->addCell(1200, $bg)->addText(number_format($base, 0, ',', '.'), ['size' => 8.5], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);
            $detailTable->addCell(1400, $bg)->addText(number_format($amountApproved, 0, ',', '.'), ['bold' => true, 'size' => 8.5, 'color' => '0F766E'], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);

            $no++;
        }

        // Summary Row 1: Total Realisasi Biaya BBM
        $detailTable->addRow(300);
        $totCell = $detailTable->addCell(6400, ['bgColor' => 'E2E8F0', 'gridSpan' => 5]);
        $totCell->addText('TOTAL BIAYA BBM', ['bold' => true, 'size' => 9], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);

        $detailTable->addCell(1200, ['bgColor' => 'E2E8F0'])
            ->addText("Rp " . number_format($totalNota, 0, ',', '.'), ['bold' => true, 'size' => 9], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);

        $detailTable->addCell(1400, ['bgColor' => 'E2E8F0'])
            ->addText("Rp " . number_format($totalDisetujui, 0, ',', '.'), ['bold' => true, 'size' => 9, 'color' => '0F766E'], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);

        // Summary Row 2: Budget Diberikan di Awal
        $detailTable->addRow(280);
        $bgtCell = $detailTable->addCell(6400, ['bgColor' => 'F1F5F9', 'gridSpan' => 5]);
        $bgtCell->addText('BUDGET AWAL', ['bold' => true, 'size' => 9], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);

        $detailTable->addCell(2600, ['bgColor' => 'F1F5F9', 'gridSpan' => 2])
            ->addText("Rp " . number_format($bbmBudget, 0, ',', '.'), ['bold' => true, 'size' => 9, 'color' => '1E293B'], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);

        // Summary Row 3: Pengembalian Dana / Over Budget
        $sisaDiff = (float)$bbmBudget - (float)$totalDisetujui;
        $isOverBudget = ($sisaDiff < 0);

        if ($isOverBudget) {
            $rowTitle = 'OVER BUDGET';
            $valPengembalianStr = "Rp " . number_format(abs($sisaDiff), 0, ',', '.');
            $colorPengembalian = 'DC2626';
            $bgColorRow = 'FEE2E2';
        } elseif ($sisaDiff > 0) {
            $rowTitle = 'PENGEMBALIAN DANA';
            $valPengembalianStr = "Rp " . number_format($sisaDiff, 0, ',', '.');
            $colorPengembalian = '0F766E';
            $bgColorRow = 'E2E8F0';
        } else {
            $rowTitle = 'PENGEMBALIAN DANA';
            $valPengembalianStr = "Rp 0 (Selesai / Tepat Sesuai Budget)";
            $colorPengembalian = '0F766E';
            $bgColorRow = 'E2E8F0';
        }

        $detailTable->addRow(300);
        $retCell = $detailTable->addCell(6400, ['bgColor' => $bgColorRow, 'gridSpan' => 5]);
        $retCell->addText($rowTitle, ['bold' => true, 'size' => 9, 'color' => $isOverBudget ? 'DC2626' : '1E293B'], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);

        $detailTable->addCell(2600, ['bgColor' => $bgColorRow, 'gridSpan' => 2])
            ->addText($valPengembalianStr, ['bold' => true, 'size' => 9, 'color' => $colorPengembalian], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);

        $section->addTextBreak(1);

        // Keterangan Data Pengembalian
        if ($bbmBudget > 0) {
            if ($sisaDiff > 0) {
                $section->addText(
                    "Catatan Pengembalian: Karyawan telah menerima budget operasional BBM di awal sebesar Rp " . number_format($bbmBudget, 0, ',', '.') . ". Berdasarkan total realisasi klaim BBM yang disetujui sebesar Rp " . number_format($totalDisetujui, 0, ',', '.') . ", maka sisa dana operasional yang WAJIB DIKEMBALIKAN ke perusahaan adalah sebesar Rp " . number_format($sisaDiff, 0, ',', '.') . ".",
                    ['italic' => true, 'size' => 8.5, 'color' => '334155'],
                    ['spaceAfter' => 80]
                );
            } elseif ($sisaDiff < 0) {
                $section->addText(
                    "Catatan Over Budget: Karyawan telah menerima budget operasional BBM di awal sebesar Rp " . number_format($bbmBudget, 0, ',', '.') . ". Total realisasi klaim BBM yang diajukan sebesar Rp " . number_format($totalDisetujui, 0, ',', '.') . " melebihi budget awal (Over Budget / Kelebihan pengeluaran: Rp " . number_format(abs($sisaDiff), 0, ',', '.') . ").",
                    ['italic' => true, 'size' => 8.5, 'color' => 'DC2626'],
                    ['spaceAfter' => 80]
                );
            } else {
                $section->addText(
                    "Catatan Pengembalian: Karyawan telah menerima budget operasional BBM di awal sebesar Rp " . number_format($bbmBudget, 0, ',', '.') . ". Total realisasi klaim BBM tepat sesuai budget yang diberikan (Nihil pengembalian).",
                    ['italic' => true, 'size' => 8.5, 'color' => '334155'],
                    ['spaceAfter' => 80]
                );
            }
        }

        // SECTION 2: LAMPIRAN FOTO ODOMETER & BUKTI PENGISIAN BBM (GABUNGAN BEFORE & AFTER PER TRANSAKSI)
        $bbmPairs = $claim->getBbmPairCombinedPhotos();

        $generalPhotos = [];
        if (is_array($claim->photos)) {
            foreach ($claim->photos as $p) {
                if ($p && Storage::disk('public')->exists($p)) {
                    $generalPhotos[] = Storage::disk('public')->path($p);
                }
            }
        }

        if (!empty($bbmPairs) || !empty($generalPhotos)) {
            $section->addPageBreak();
            $this->addHeaderKop($section);
            $section->addTextBreak(1);
            $section->addText('LAMPIRAN DOKUMENTASI FOTO ODOMETER & NOTA BBM', ['bold' => true, 'size' => 10.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $section->addText('(Foto Before & After Digabung Per Transaksi Pengisian BBM)', ['size' => 8.5, 'color' => '64748B'], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);

            foreach ($bbmPairs as $pair) {
                $section->addText($pair['label'], ['bold' => true, 'size' => 9, 'color' => '1E293B'], [
                    'alignment' => Jc::LEFT,
                    'spaceBefore' => 120,
                    'spaceAfter' => 40,
                ]);

                if (!empty($pair['combined_path']) && file_exists($pair['combined_path'])) {
                    $section->addImage($pair['combined_path'], [
                        'width' => 460,
                        'height' => 220,
                        'alignment' => Jc::CENTER,
                        'wrappingStyle' => 'inline',
                    ]);
                    $section->addTextBreak(1);
                }
            }

            if (!empty($generalPhotos)) {
                $section->addText('DOKUMEN NOTA FISIK / BUKTI PENDUKUNG :', ['bold' => true, 'size' => 9, 'color' => '1E293B'], ['spaceBefore' => 100, 'spaceAfter' => 40]);
                foreach ($generalPhotos as $pPath) {
                    $section->addImage($pPath, [
                        'width' => 400,
                        'height' => 260,
                        'alignment' => Jc::CENTER,
                        'wrappingStyle' => 'inline',
                    ]);
                    $section->addTextBreak(1);
                }
            }
        }

        // SECTION 3: LAMPIRAN BUKTI TRANSFER PENGEMBALIAN DANA (NOTA / STRUK BALIK)
        $returnProof = $claim->return_transfer_proof;
        if ($returnProof && Storage::disk('public')->exists($returnProof)) {
            $section->addPageBreak();
            $this->addHeaderKop($section);
            $section->addTextBreak(1);
            $section->addText('LAMPIRAN BUKTI TRANSFER PENGEMBALIAN DANA KE FINANCE', ['bold' => true, 'size' => 10.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $section->addText('(Bukti Transfer / Struk Setoran Pengembalian Sisa Budget Operasional BBM)', ['size' => 8.5, 'color' => '64748B'], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);

            $retTable = $section->addTable([
                'alignment' => JcTable::CENTER,
                'unit' => TblWidth::PERCENT,
                'width' => 100 * 50,
                'borders' => [
                    'allBorders' => ['borderStyle' => 'thin', 'color' => 'CBD5E1'],
                ],
            ]);

            $retTable->addRow(240);
            $retInfoCell = $retTable->addCell(9000, ['bgColor' => 'F8FAFC']);
            $nominalRetStr = $claim->return_transfer_amount ? "Rp " . number_format((float)$claim->return_transfer_amount, 0, ',', '.') : ($sisaDiff > 0 ? "Rp " . number_format($sisaDiff, 0, ',', '.') : '-');
            $tglRetStr = $claim->return_transferred_at ? date('d/m/Y H:i', strtotime($claim->return_transferred_at)) : '-';
            $catatanRet = $claim->return_transfer_notes ?: '-';

            $retInfoCell->addText("Nominal Pengembalian : {$nominalRetStr}", ['bold' => true, 'size' => 9, 'color' => '0F766E'], ['spaceAfter' => 20]);
            $retInfoCell->addText("Tanggal Transfer       : {$tglRetStr}", ['size' => 9], ['spaceAfter' => 20]);
            $retInfoCell->addText("Catatan Transfer       : {$catatanRet}", ['size' => 9, 'italic' => true], ['spaceAfter' => 20]);

            $section->addTextBreak(1);

            $proofFullPath = Storage::disk('public')->path($returnProof);
            $section->addImage($proofFullPath, [
                'width' => 420,
                'height' => 480,
                'alignment' => Jc::CENTER,
                'wrappingStyle' => 'inline',
            ]);
        }

        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName);
        $fileName = "Form_Klaim_BBM_{$cleanName}_" . date('Ymd_His') . ".docx";
        $tempPath = storage_path("app/public/{$fileName}");

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Add Company Header with Logo & Double Border Line
     */
    protected function addHeaderKop($section): void
    {
        $headerTable = $section->addTable([
            'alignment' => JcTable::CENTER,
            'unit' => TblWidth::PERCENT,
            'width' => 100 * 50,
        ]);

        $headerTable->addRow(500);

        // Logo Cell (Left)
        $logoCell = $headerTable->addCell(2200);
        $logoPath = public_path('images/company-logo.png');
        if (file_exists($logoPath)) {
            $logoCell->addImage($logoPath, [
                'width' => 95,
                'height' => 52,
                'alignment' => Jc::START,
            ]);
        }

        // Title Cell (Center/Right)
        $titleCell = $headerTable->addCell(6800);
        $titleCell->addText('PT. MEDIA SELULAR INDONESIA', ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $titleCell->addText('PUSAT GROSIR & ECERAN', ['size' => 9.5, 'italic' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 10]);
        $titleCell->addText('Jl. Tuparev No. 109F Kertawinangun, Kedawung - Cirebon 45153', ['size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);

        // Double Horizontal Divider
        $lineTable = $section->addTable([
            'alignment' => JcTable::CENTER,
            'unit' => TblWidth::PERCENT,
            'width' => 100 * 50,
            'borderBottomSize' => 18,
            'borderBottomColor' => '1E293B',
        ]);
        $lineTable->addRow(10);
        $lineTable->addCell(9000);
    }
}

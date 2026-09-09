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

class BbmBeritaAcaraWordExportService
{
    public function export(Claim $claim, array $customData = []): BinaryFileResponse
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);

        $emp = $claim->effective_employee;
        $empName = $customData['name'] ?? ($emp?->name ?? 'JOHAN ARIF');
        $empPosition = $customData['position'] ?? ($emp?->position_name ?? 'ASM');
        $claimDate = $claim->claim_date ? $claim->claim_date->translatedFormat('d F Y') : date('d F Y');
        $phone = $customData['phone'] ?? ($claim->ba_phone ?? '+62 821-7510-0062');
        $division = $customData['division'] ?? ($claim->ba_division ?? 'Distribusi Sales - TECNO');
        $appr1 = $customData['approver_1'] ?? ($claim->ba_approver_1 ?? 'Agus Supangat');
        $appr2 = $customData['approver_2'] ?? ($claim->ba_approver_2 ?? 'Alb. Maria Adi Nugroho');
        $appr3 = $customData['approver_3'] ?? ($claim->ba_approver_3 ?? 'Yoga Prima Hadi');

        // SECTION 1: HALAMAN UTAMA BERITA ACARA
        $section = $phpWord->addSection([
            'marginTop' => 720,
            'marginBottom' => 720,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);

        $this->addHeaderKop($section);

        $section->addTextBreak(1);

        // Title
        $section->addText('BERITA ACARA', ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $section->addText('KETIDAKSESUAIAN PEMBELIAN BBM', ['bold' => true, 'size' => 11, 'underline' => 'single'], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);

        // Opening Text
        $section->addText("Bahwa tanggal {$claimDate} saya yang bertanda tangan dibawah ini:", ['size' => 10], ['spaceAfter' => 60]);

        // Table Data Pemohon
        $infoTable = $section->addTable([
            'alignment' => JcTable::START,
            'unit' => TblWidth::PERCENT,
            'width' => 100 * 50,
        ]);

        $rows = [
            ['Nama', ": {$empName}"],
            ['No. Telp/HP', ": {$phone}"],
            ['Badan Usaha', ": PT. MSI"],
            ['Jabatan', ": {$empPosition}"],
            ['Divisi', ": {$division}"],
        ];

        foreach ($rows as $r) {
            $infoTable->addRow(240);
            $infoTable->addCell(2200)->addText($r[0], ['bold' => true, 'size' => 9.5], ['spaceAfter' => 20]);
            $infoTable->addCell(6800)->addText($r[1], ['size' => 9.5], ['spaceAfter' => 20]);
        }

        $section->addTextBreak(1);

        // Narasi Pertanggungjawaban
        $narrative1 = "Sesuai ketentuan pembelian BBM yang mensyaratkan nominal transaksi berakhir dengan Rp5.000 (lima ribu rupiah), pembelian BBM dimaksud telah dilakukan sesuai ketentuan tersebut. Namun demikian, struk/nota fisik pembelian tidak tersedia karena tidak tercetak/tidak diberikan oleh pihak SPBU. Berdasarkan hasil pengecekan terhadap berita acara, foto kilometer, serta kondisi kendaraan di lapangan, nominal pengisian telah sesuai dengan kebutuhan operasional kendaraan pada saat pengisian. Sehubungan dengan hal tersebut, mohon persetujuan (approval) atas klaim pembelian BBM dimaksud. Adapun berita acara dan foto kilometer yang dilampirkan telah sesuai dan lengkap sebagai dokumen pendukung klaim.";
        $section->addText($narrative1, ['size' => 9.5], ['alignment' => Jc::BOTH, 'spaceAfter' => 80]);

        $narrative2 = "Demikian berita acara ini dibuat dengan sebenar-benarnya dengan penuh kesadaran dan tanggung jawab tanpa paksaan dari pihak manapun.";
        $section->addText($narrative2, ['size' => 9.5], ['alignment' => Jc::BOTH, 'spaceAfter' => 140]);

        // Tanda Tangan Block
        $sigTable = $section->addTable([
            'alignment' => JcTable::CENTER,
            'unit' => TblWidth::PERCENT,
            'width' => 100 * 50,
        ]);

        $sigTable->addRow(240);
        $leftCell = $sigTable->addCell(5200);
        $rightCell = $sigTable->addCell(3800);

        $leftCell->addText('Yang Menyetujui (ttd) :', ['bold' => true, 'size' => 9.5], ['spaceAfter' => 60]);
        $leftCell->addText("1. {$appr1}     ( approve by WA )", ['size' => 9], ['spaceAfter' => 60]);
        $leftCell->addText("2. {$appr2}   (........................................)", ['size' => 9], ['spaceAfter' => 60]);
        $leftCell->addText("3. {$appr3}   (........................................)", ['size' => 9], ['spaceAfter' => 60]);

        $rightCell->addText('Yang Membuat,', ['size' => 9.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $rightCell->addText('Pemohon', ['bold' => true, 'size' => 9.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);

        // Embed digital signature pemohon jika ada
        $sigImg = $emp?->signature_image;
        if ($sigImg && Storage::disk('public')->exists($sigImg)) {
            $sigPath = Storage::disk('public')->path($sigImg);
            $rightCell->addImage($sigPath, [
                'width' => 100,
                'height' => 45,
                'alignment' => Jc::CENTER,
            ]);
        } else {
            $rightCell->addTextBreak(2);
        }
        $rightCell->addText("({$empName})", ['bold' => true, 'size' => 9.5], ['alignment' => Jc::CENTER]);

        // SECTION 2: LAMPIRAN SCREENSHOT WA PERSATUJUAN (JIKA ADA)
        $waPhoto = $claim->ba_wa_proof_photo;
        if ($waPhoto && Storage::disk('public')->exists($waPhoto)) {
            $section->addPageBreak();
            $this->addHeaderKop($section);
            $section->addTextBreak(1);
            $section->addText('LAMPIRAN SCREENSHOT PERSETUJUAN WHATSAPP (WA APPROVAL)', ['bold' => true, 'size' => 10.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);

            $waFullPath = Storage::disk('public')->path($waPhoto);
            $section->addImage($waFullPath, [
                'width' => 420,
                'height' => 500,
                'alignment' => Jc::CENTER,
                'wrappingStyle' => 'inline',
            ]);
        }

        // SECTION 3: LAMPIRAN FOTO KILOMETER & BBM (BEFORE-AFTER)
        $allBbmPhotos = [];
        if ($claim->bbm_photo_combined && Storage::disk('public')->exists($claim->bbm_photo_combined)) {
            $allBbmPhotos[] = $claim->bbm_photo_combined;
        } elseif ($claim->bbm_photo_before || $claim->bbm_photo_after) {
            if ($claim->bbm_photo_before && Storage::disk('public')->exists($claim->bbm_photo_before)) $allBbmPhotos[] = $claim->bbm_photo_before;
            if ($claim->bbm_photo_after && Storage::disk('public')->exists($claim->bbm_photo_after)) $allBbmPhotos[] = $claim->bbm_photo_after;
        }

        if (!empty($allBbmPhotos)) {
            $section->addPageBreak();
            $this->addHeaderKop($section);
            $section->addTextBreak(1);
            $section->addText('LAMPIRAN FOTO NOTA BENSIN & FOTO KILOMETER (BEFORE-AFTER)', ['bold' => true, 'size' => 10.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);

            foreach ($allBbmPhotos as $photo) {
                $photoPath = Storage::disk('public')->path($photo);
                $section->addImage($photoPath, [
                    'width' => 440,
                    'height' => 320,
                    'alignment' => Jc::CENTER,
                    'wrappingStyle' => 'inline',
                ]);
                $section->addTextBreak(1);
            }
        }

        // SECTION 4: LAMPIRAN STRUK / FOTO LAINNYA
        if (is_array($claim->photos) && count($claim->photos) > 0) {
            $section->addPageBreak();
            $this->addHeaderKop($section);
            $section->addTextBreak(1);
            $section->addText('LAMPIRAN BUKTI TRANSAKSI PENGISIAN BBM', ['bold' => true, 'size' => 10.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);

            foreach ($claim->photos as $p) {
                if (Storage::disk('public')->exists($p)) {
                    $pPath = Storage::disk('public')->path($p);
                    $section->addImage($pPath, [
                        'width' => 400,
                        'height' => 300,
                        'alignment' => Jc::CENTER,
                        'wrappingStyle' => 'inline',
                    ]);
                    $section->addTextBreak(1);
                }
            }
        }

        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName);
        $fileName = "BA_KETIDAKSESUAIAN_BBM_{$cleanName}_" . date('Ymd_His') . ".docx";
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
        $titleCell->addText('Jl. Tuparev No. 109F - Cirebon 45153 Jawa Barat', ['size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 10]);
        $titleCell->addText('No. Telp. 0231 - 8332833', ['size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);

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

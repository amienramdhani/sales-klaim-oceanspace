<?php

namespace App\Services;

use App\Models\AttendanceDoc;
use App\Models\Employee;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceWordExportService
{
    public function export(AttendanceDoc $doc): BinaryFileResponse
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Aptos');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop' => 700,
            'marginBottom' => 700,
            'marginLeft' => 900,
            'marginRight' => 900,
        ]);

        // Header & Title: "DAFTAR HADIR MEETING"
        $titleStyle = ['name' => 'Aptos', 'bold' => true, 'size' => 13, 'color' => '1E293B'];
        $section->addText('DAFTAR HADIR MEETING', $titleStyle, ['alignment' => Jc::CENTER, 'spaceAfter' => 140]);

        // Information Box (PERIHAL, TGL, TEMPAT)
        $infoTableStyle = [
            'cellMargin' => 30,
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
        ];
        $phpWord->addTableStyle('InfoTable', $infoTableStyle);
        $infoTable = $section->addTable('InfoTable');

        $formattedDate = $doc->date ? $doc->date->translatedFormat('l, d F Y') : '-';

        // Row 1: PERIHAL
        $infoTable->addRow();
        $infoTable->addCell(1800)->addText('PERIHAL', ['name' => 'Aptos', 'bold' => true, 'size' => 9]);
        $infoTable->addCell(7400)->addText(': ' . strtoupper($doc->purpose), ['name' => 'Aptos', 'bold' => true, 'size' => 9]);

        // Row 2: TGL (TANGGAL)
        $infoTable->addRow();
        $infoTable->addCell(1800)->addText('TGL (TANGGAL)', ['name' => 'Aptos', 'bold' => true, 'size' => 9]);
        $infoTable->addCell(7400)->addText(': ' . $formattedDate, ['name' => 'Aptos', 'size' => 9]);

        // Row 3: TEMPAT
        $infoTable->addRow();
        $infoTable->addCell(1800)->addText('TEMPAT', ['name' => 'Aptos', 'bold' => true, 'size' => 9]);
        $infoTable->addCell(7400)->addText(': ' . $doc->place, ['name' => 'Aptos', 'size' => 9]);

        $section->addTextBreak(0.5);

        // Participants Table (Tabel Daftar Hadir)
        $section->addText('Daftar Hadir Peserta Meeting:', ['name' => 'Aptos', 'bold' => true, 'size' => 9.5], ['spaceAfter' => 60]);

        $partTableStyle = [
            'borderColor' => '94A3B8',
            'borderSize' => 5,
            'cellMargin' => 50,
            'alignment' => JcTable::CENTER,
        ];
        $phpWord->addTableStyle('ParticipantsTable', $partTableStyle);
        $partTable = $section->addTable('ParticipantsTable');

        // Header Row (Columns: No, Nama Peserta, Tanda Tangan Ganjil, Tanda Tangan Genap)
        $partTable->addRow(320);
        $headerCellStyle = ['bgColor' => '1E293B'];
        $headerTextStyle = ['name' => 'Aptos', 'bold' => true, 'color' => 'FFFFFF', 'size' => 9];

        $partTable->addCell(600, $headerCellStyle)->addText('No', $headerTextStyle, ['alignment' => Jc::CENTER]);
        $partTable->addCell(5200, $headerCellStyle)->addText('Nama Peserta', $headerTextStyle, ['alignment' => Jc::CENTER]);
        $partTable->addCell(1900, $headerCellStyle)->addText('Tanda Tangan', $headerTextStyle, ['alignment' => Jc::CENTER]);
        $partTable->addCell(1900, $headerCellStyle)->addText('', $headerTextStyle, ['alignment' => Jc::CENTER]);

        $participants = $doc->participants;
        $no = 1;

        // Preload all employee signatures
        $allEmployees = Employee::all();

        if ($participants->count() > 0) {
            foreach ($participants as $p) {
                $partTable->addRow(380);
                $partTable->addCell(600)->addText((string)$no, ['name' => 'Aptos', 'size' => 9], ['alignment' => Jc::CENTER]);
                $partTable->addCell(5200)->addText($p->name, ['name' => 'Aptos', 'size' => 9]);

                // Match participant by name in employees for signature if available
                $pName = trim(strtolower($p->name));
                $matchedEmp = $allEmployees->first(function ($emp) use ($pName) {
                    $eName = trim(strtolower($emp->name));
                    return $eName === $pName || str_contains($eName, $pName) || str_contains($pName, $eName);
                });

                $sigFile = $matchedEmp?->signature_image;
                $sigFullPath = $sigFile ? Storage::disk('public')->path($sigFile) : null;
                $hasSignatureImage = $sigFullPath && file_exists($sigFullPath);

                if ($no % 2 !== 0) {
                    // Ganjil -> Kolom Kiri
                    $cellLeft = $partTable->addCell(1900);
                    if ($hasSignatureImage) {
                        $cellLeft->addImage($sigFullPath, ['width' => 60, 'height' => 26, 'alignment' => Jc::CENTER]);
                    } else {
                        $cellLeft->addText("{$no}. ....................", ['name' => 'Aptos', 'size' => 8.5]);
                    }
                    $partTable->addCell(1900)->addText('', ['name' => 'Aptos', 'size' => 8.5]);
                } else {
                    // Genap -> Kolom Kanan
                    $partTable->addCell(1900)->addText('', ['name' => 'Aptos', 'size' => 8.5]);
                    $cellRight = $partTable->addCell(1900);
                    if ($hasSignatureImage) {
                        $cellRight->addImage($sigFullPath, ['width' => 60, 'height' => 26, 'alignment' => Jc::CENTER]);
                    } else {
                        $cellRight->addText("{$no}. ....................", ['name' => 'Aptos', 'size' => 8.5]);
                    }
                }
                $no++;
            }
        }

        // Fill up to at least 4 rows if few participants
        while ($no <= max(4, $participants->count())) {
            $partTable->addRow(380);
            $partTable->addCell(600)->addText((string)$no, ['name' => 'Aptos', 'size' => 9], ['alignment' => Jc::CENTER]);
            $partTable->addCell(5200)->addText('', ['name' => 'Aptos', 'size' => 9]);

            if ($no % 2 !== 0) {
                $partTable->addCell(1900)->addText("{$no}. ....................", ['name' => 'Aptos', 'size' => 8.5]);
                $partTable->addCell(1900)->addText('', ['name' => 'Aptos', 'size' => 8.5]);
            } else {
                $partTable->addCell(1900)->addText('', ['name' => 'Aptos', 'size' => 8.5]);
                $partTable->addCell(1900)->addText("{$no}. ....................", ['name' => 'Aptos', 'size' => 8.5]);
            }
            $no++;
        }

        // Photo Documentation: Tidak dipaksa resize distortif, mempertahankan aspect ratio, format in front text / mudah digeser
        $photos = array_merge(
            is_array($doc->photos) ? $doc->photos : [],
            is_array($doc->claim?->photos) ? $doc->claim->photos : []
        );
        if ($doc->claim?->bbm_photo_combined) {
            $photos[] = $doc->claim->bbm_photo_combined;
        }
        $photos = array_values(array_unique($photos));

        if (!empty($photos)) {
            $section->addTextBreak(0.5);
            $section->addText('Dokumentasi Foto Pertemuan & Lampiran Bukti:', ['name' => 'Aptos', 'bold' => true, 'size' => 9, 'color' => '1E293B'], ['spaceAfter' => 40]);

            foreach ($photos as $photoPath) {
                $fullPath = Storage::disk('public')->path($photoPath);

                if (file_exists($fullPath)) {
                    $imgSize = @getimagesize($fullPath);
                    $imgWidth = 350;
                    $imgHeight = 220;

                    if ($imgSize && $imgSize[0] > 0 && $imgSize[1] > 0) {
                        $origW = $imgSize[0];
                        $origH = $imgSize[1];
                        $maxW = 420;
                        if ($origW > $maxW) {
                            $imgWidth = $maxW;
                            $imgHeight = (int)(($origH / $origW) * $maxW);
                        } else {
                            $imgWidth = $origW;
                            $imgHeight = $origH;
                        }
                    }

                    $section->addImage($fullPath, [
                        'width' => $imgWidth,
                        'height' => $imgHeight,
                        'alignment' => Jc::CENTER,
                        'spaceAfter' => 60,
                        'wrappingStyle' => 'infront',
                    ]);
                }
            }
        }

        // Save to temporary storage
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $cleanPurpose = substr(preg_replace('/[^A-Za-z0-9_\-]/', '_', $doc->purpose), 0, 30);
        $fileName = "Daftar_Hadir_{$doc->id}_{$cleanPurpose}.docx";
        $filePath = "{$tempDir}/{$fileName}";

        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filePath);

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }
}

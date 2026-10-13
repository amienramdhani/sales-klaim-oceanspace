<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\Employee;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendanceImageGeneratorService
{
    /**
     * Generate an attendance sheet PNG image for a specific entertain line item
     *
     * @param array $itemData The item data containing attendance information
     * @param Claim|null $claim The parent claim model
     * @param int $itemIndex The index of the item
     * @return string|null Relative storage path of the generated image (e.g. 'attendance-generated/...')
     */
    public function generateForClaimItem(array $itemData, ?Claim $claim = null, int $itemIndex = 0): ?string
    {
        $participants = $itemData['attendance_participants'] ?? [];
        if (empty($participants) && empty($itemData['attendance_purpose'])) {
            return null;
        }

        // Determine font paths
        $fontRegular = $this->resolveFont(false);
        $fontBold = $this->resolveFont(true);

        // Gather display info
        $emp = $claim?->effective_employee;
        $empName = $emp?->name ?? (auth()->user()?->name ?? 'Pemohon Klaim');
        $purpose = !empty($itemData['attendance_purpose']) ? $itemData['attendance_purpose'] : ($itemData['purpose'] ?? 'Pertemuan / Entertain');
        $place = !empty($itemData['attendance_place']) ? $itemData['attendance_place'] : ($itemData['note'] ?? '-');
        $city = !empty($itemData['city']) ? $itemData['city'] : ($claim?->city ?? '-');
        $dateStr = !empty($itemData['attendance_date']) 
            ? \Carbon\Carbon::parse($itemData['attendance_date'])->translatedFormat('d F Y')
            : (!empty($itemData['receipt_date']) ? \Carbon\Carbon::parse($itemData['receipt_date'])->translatedFormat('d F Y') : date('d F Y'));
        $subtype = $itemData['entertain_subtype'] ?? ($claim?->entertain_subtype ?? 'Makan');
        $notes = $itemData['attendance_notes'] ?? '';
        $claimUid = $claim?->_uid ?: 'DRAFT-' . ($claim?->id ?? 'NEW');

        // Dimensions
        $width = 1000;
        $participantCount = max(count($participants), 1);
        $rowHeight = 36;
        $tableHeight = 35 + ($participantCount * $rowHeight);
        $infoBoxHeight = !empty($notes) ? 120 : 100;
        $height = 240 + $infoBoxHeight + $tableHeight + 110;

        $img = imagecreatetruecolor($width, $height);

        // Palette
        $white = imagecolorallocate($img, 255, 255, 255);
        $navy = imagecolorallocate($img, 30, 58, 138); // #1e3a8a
        $slate900 = imagecolorallocate($img, 15, 23, 42); // #0f172a
        $slate800 = imagecolorallocate($img, 30, 41, 59);
        $slate700 = imagecolorallocate($img, 51, 65, 85);
        $slate500 = imagecolorallocate($img, 100, 116, 139);
        $slate200 = imagecolorallocate($img, 226, 232, 240);
        $slate100 = imagecolorallocate($img, 241, 245, 249);
        $slate50 = imagecolorallocate($img, 248, 250, 252);
        $emerald = imagecolorallocate($img, 5, 150, 105);

        // Fill background
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        // Outer borders
        imagesetthickness($img, 2);
        imagerectangle($img, 18, 18, $width - 18, $height - 18, $slate800);
        imagesetthickness($img, 1);

        // Top Banner (Navy Header)
        imagefilledrectangle($img, 20, 20, $width - 20, 84, $navy);
        imagettftext($img, 16, 0, 48, 58, $white, $fontBold, 'DAFTAR HADIR MEETING');
        imagettftext($img, 9.5, 0, $width - 320, 48, $white, $fontRegular, 'PT. MEDIA SELULAR INDONESIA');
        imagettftext($img, 8.5, 0, $width - 320, 68, $slate200, $fontRegular, "Ref: {$claimUid} | Item #" . ($itemIndex + 1));

        // Info box (Perihal, Tanggal, Tempat)
        $boxY = 96;
        imagefilledrectangle($img, 36, $boxY, $width - 36, $boxY + $infoBoxHeight, $slate50);
        imagerectangle($img, 36, $boxY, $width - 36, $boxY + $infoBoxHeight, $slate200);

        imagettftext($img, 10.5, 0, 56, $boxY + 28, $slate800, $fontBold, 'Perihal   :');
        imagettftext($img, 10.5, 0, 160, $boxY + 28, $slate900, $fontBold, strtoupper($purpose));

        imagettftext($img, 10.5, 0, 56, $boxY + 56, $slate800, $fontBold, 'Tanggal   :');
        imagettftext($img, 10.5, 0, 160, $boxY + 56, $slate900, $fontRegular, "{$dateStr}  (Pemohon: {$empName})");

        imagettftext($img, 10.5, 0, 56, $boxY + 84, $slate800, $fontBold, 'Tempat    :');
        $placeFull = $place . ($city && $city !== '-' ? " ({$city})" : "");
        imagettftext($img, 10.5, 0, 160, $boxY + 84, $slate900, $fontRegular, $placeFull);

        if (!empty($notes)) {
            imagettftext($img, 9.5, 0, 56, $boxY + 108, $slate700, $fontBold, 'Catatan   :');
            imagettftext($img, 9.5, 0, 160, $boxY + 108, $slate800, $fontRegular, Str::limit($notes, 85));
        }

        // Table Header (Matching mockup: No | Nama Peserta | Tanda Tangan)
        $tableY = $boxY + $infoBoxHeight + 16;
        imagefilledrectangle($img, 36, $tableY, $width - 36, $tableY + 36, $slate900);

        imagettftext($img, 10.5, 0, 56, $tableY + 24, $white, $fontBold, 'No');
        imagettftext($img, 10.5, 0, 140, $tableY + 24, $white, $fontBold, 'Nama Peserta');
        imagettftext($img, 10.5, 0, 710, $tableY + 24, $white, $fontBold, 'Tanda Tangan');

        // Table Rows
        $currY = $tableY + 36;
        if (!empty($participants)) {
            foreach ($participants as $idx => $part) {
                $pName = !empty($part['name']) ? $part['name'] : '-';
                $pPos = !empty($part['position']) ? $part['position'] : '';
                $pStatus = !empty($part['status']) ? strtoupper($part['status']) : 'HADIR';

                $bg = ($idx % 2 === 0) ? $white : $slate50;
                imagefilledrectangle($img, 36, $currY, $width - 36, $currY + $rowHeight, $bg);
                imagerectangle($img, 36, $currY, $width - 36, $currY + $rowHeight, $slate200);

                // Column vertical dividers (No separator, Name separator)
                imageline($img, 110, $currY, 110, $currY + $rowHeight, $slate200);
                imageline($img, 680, $currY, 680, $currY + $rowHeight, $slate200);

                // No
                imagettftext($img, 10, 0, 65, $currY + 23, $slate900, $fontRegular, (string)($idx + 1));

                // Nama Peserta + Position
                imagettftext($img, 10, 0, 130, $currY + 23, $slate900, $fontBold, Str::limit($pName, 42));
                if (!empty($pPos)) {
                    imagettftext($img, 8.5, 0, 480, $currY + 23, $slate500, $fontRegular, Str::limit($pPos, 26));
                }

                // Tanda Tangan / Paraf
                imagettftext($img, 9, 0, 720, $currY + 23, $emerald, $fontBold, "[ ✓ Paraf / {$pStatus} ]");

                $currY += $rowHeight;
            }
        } else {
            imagefilledrectangle($img, 36, $currY, $width - 36, $currY + $rowHeight, $white);
            imagerectangle($img, 36, $currY, $width - 36, $currY + $rowHeight, $slate200);
            imagettftext($img, 10, 0, 130, $currY + 23, $slate500, $fontRegular, '(Belum ada peserta ditambahkan)');
            $currY += $rowHeight;
        }

        // Table outer border
        imagesetthickness($img, 1.5);
        imagerectangle($img, 36, $tableY, $width - 36, $currY, $slate900);
        imagesetthickness($img, 1);

        // Section Title Below Table (Matching mockup: Dokumentasi Foto Pertemuan & Lampiran Bukti)
        $footerY = $currY + 26;
        imagettftext($img, 11, 0, 36, $footerY, $slate900, $fontBold, 'Dokumentasi Foto Pertemuan & Lampiran Bukti:');
        imagettftext($img, 8.5, 0, 36, $footerY + 20, $slate500, $fontRegular, 'Lampiran bukti fisik nota restoran/struk dan dokumentasi acara disematkan di bawah ini.');

        // Pemohon signature box (right side)
        imagettftext($img, 9, 0, $width - 260, $footerY, $slate700, $fontBold, 'Yang Melaporkan,');
        imagettftext($img, 9.5, 0, $width - 260, $footerY + 36, $slate900, $fontBold, $empName);

        // Save image to storage
        $directory = 'attendance-generated';
        Storage::disk('public')->makeDirectory($directory);

        $claimId = $claim?->id ?? 'new';
        $filename = "daftar_hadir_claim_{$claimId}_item_{$itemIndex}_" . time() . "_" . Str::random(6) . ".png";
        $fullPath = storage_path("app/public/{$directory}/{$filename}");

        imagepng($img, $fullPath);
        imagedestroy($img);

        return "{$directory}/{$filename}";
    }

    /**
     * Resolves an available TrueType font file
     */
    protected function resolveFont(bool $bold = false): string
    {
        $candidates = $bold ? [
            'C:/Windows/Fonts/arialbd.ttf',
            'C:/Windows/Fonts/segoeuib.ttf',
            'C:/Windows/Fonts/calibrib.ttf',
            'C:/Windows/Fonts/arial.ttf',
        ] : [
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/segoeui.ttf',
            'C:/Windows/Fonts/calibri.ttf',
        ];

        foreach ($candidates as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        return 'C:/Windows/Fonts/arial.ttf';
    }
}

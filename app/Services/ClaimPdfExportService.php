<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimPdfExportService
{
    /**
     * Convert an image file or storage path to a base64 data URI for safe DomPDF embedding
     */
    public function imageToBase64(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'data:image')) {
            return $path;
        }

        // Clean path variations
        $clean = str_replace('\\', '/', $path);
        $relative = ltrim($clean, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, 8);
        }
        if (str_starts_with($relative, 'public/')) {
            $relative = substr($relative, 7);
        }

        // 1. Check storage/public disk with multiple path candidates
        $candidates = array_unique([$path, $relative, ltrim($clean, '/')]);
        foreach ($candidates as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                try {
                    $contents = Storage::disk('public')->get($candidate);
                    $mime = Storage::disk('public')->mimeType($candidate) ?: 'image/jpeg';
                    return 'data:' . $mime . ';base64,' . base64_encode($contents);
                } catch (\Throwable $e) {
                    // try next candidate
                }
            }
        }

        // 2. Check storage_path('app/public/' . $relative)
        $storageFullPath = storage_path('app/public/' . $relative);
        if (file_exists($storageFullPath) && is_file($storageFullPath)) {
            try {
                $contents = file_get_contents($storageFullPath);
                $mime = mime_content_type($storageFullPath) ?: 'image/jpeg';
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            } catch (\Throwable $e) {
                // continue
            }
        }

        // 3. Check direct filesystem path
        if (file_exists($path) && is_file($path)) {
            try {
                $contents = file_get_contents($path);
                $mime = mime_content_type($path) ?: 'image/jpeg';
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            } catch (\Throwable $e) {
                // continue
            }
        }

        // 4. Check public_path
        foreach ([$path, $relative, 'storage/' . $relative] as $pubCandidate) {
            $pubPath = public_path($pubCandidate);
            if (file_exists($pubPath) && is_file($pubPath)) {
                try {
                    $contents = file_get_contents($pubPath);
                    $mime = mime_content_type($pubPath) ?: 'image/jpeg';
                    return 'data:' . $mime . ';base64,' . base64_encode($contents);
                } catch (\Throwable $e) {
                    // continue
                }
            }
        }

        return null;
    }

    /**
     * Get company logo as base64 data URI
     */
    public function getCompanyLogoBase64(): ?string
    {
        return $this->imageToBase64('images/company-logo.png');
    }

    /**
     * Export a single claim to PDF (auto-detects Perdin, Service, BBM, Transport & Entertain)
     */
    public function exportSingleClaimPdf(Claim $claim): StreamedResponse
    {
        if ($claim->is_perdin || $claim->claim_category === 'perdin') {
            return $this->exportPerdinSinglePdf($claim);
        }

        if (in_array($claim->claim_category, ['service', 'service_motor'])) {
            return $this->exportVehicleServiceSinglePdf($claim);
        }

        $isBbm = ($claim->claim_category === 'bbm' || in_array('BBM', (array)$claim->claim_type) || str_contains(strtoupper($claim->claim_type_string ?? ''), 'BBM'));
        if ($isBbm) {
            return $this->exportBbmSinglePdf($claim);
        }

        return $this->exportTransportEntertainSinglePdf($claim);
    }

    /**
     * Export Single Transport & Entertain Claim to PDF Voucher (Portrait A4)
     */
    public function exportTransportEntertainSinglePdf(Claim $claim): StreamedResponse
    {
        $emp = $claim->effective_employee;
        $empName = $emp?->name ?? 'Karyawan';
        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName);
        $uid = $claim->_uid ?: 'DRAFT';
        $filename = "Klaim_Transport_Entertain_{$cleanName}_{$uid}_" . date('Ymd_His') . ".pdf";

        // Collect photos as base64
        $photosBase64 = [];
        if (is_array($claim->photos)) {
            foreach ($claim->photos as $p) {
                $b64 = $this->imageToBase64($p);
                if ($b64) {
                    $photosBase64[] = $b64;
                }
            }
        }

        // Signature (Pemohon)
        $signatureBase64 = null;
        if ($emp && $emp->signature_image) {
            $signatureBase64 = $this->imageToBase64($emp->signature_image);
        }

        // TTD Atasan Langsung (Sales/ASM -> RGM; RGM -> Head of Sales Pak Jejen)
        $roleName = strtoupper($emp?->role?->code ?? $emp?->role?->name ?? $emp?->position ?? '');
        $isRgm = str_contains($roleName, 'RGM') || ($claim->user && $claim->user->isRgm());

        $signatureSupervisorBase64 = null;
        if ($isRgm) {
            $atasanTitle = 'Disetujui Head of Sales';
            $atasanName = $claim->approvedByJejen?->name ?? 'Pak Jejen';
            $atasanPosition = 'Head of Sales';
            $isApprovedByAtasan = !empty($claim->approved_by_jejen_at) || !empty($claim->approved_by_jejen_id);
            if ($claim->approvedByJejen?->employee?->signature_image) {
                $signatureSupervisorBase64 = $this->imageToBase64($claim->approvedByJejen->employee->signature_image);
            }
        } else {
            $atasanTitle = 'Disetujui Atasan (RGM)';
            $atasanUser = $claim->approvedByRgm ?? $claim->approvedByAsm;
            $atasanName = $atasanUser?->name ?? 'RGM Wilayah';
            $atasanPosition = 'Regional General Manager';
            $isApprovedByAtasan = !empty($claim->approved_by_rgm_at) || !empty($claim->approved_by_asm_at) || !empty($claim->approved_by_rgm_id);
            if ($atasanUser?->employee?->signature_image) {
                $signatureSupervisorBase64 = $this->imageToBase64($atasanUser->employee->signature_image);
            }
        }

        $logoBase64 = $this->getCompanyLogoBase64();
        $lineItems = $claim->getLineItems();

        // Collect attendance details & documentation photos from items
        $attendanceList = [];
        $attendanceDocsBase64 = [];

        foreach ($lineItems as $idx => $item) {
            $isEntertain = ($item['claim_type'] ?? '') === 'Entertain';
            $hasAttData = !empty($item['attendance_participants']) || !empty($item['attendance_purpose']);

            if ($isEntertain && $hasAttData) {
                $processedParticipants = [];
                foreach (($item['attendance_participants'] ?? []) as $p) {
                    $pName = trim($p['name'] ?? '');
                    $pPos = $p['position'] ?? 'Peserta';
                    $pStatus = $p['status'] ?? 'HADIR';
                    $pSigBase64 = null;

                    // 1. Participant uploaded signature
                    if (!empty($p['signature'])) {
                        $pSigBase64 = $this->imageToBase64($p['signature']);
                    }
                    if (!$pSigBase64 && !empty($p['signature_image'])) {
                        $pSigBase64 = $this->imageToBase64($p['signature_image']);
                    }

                    // 2. Check if participant matches an Employee record with signature_image
                    if (!$pSigBase64 && !empty($pName)) {
                        $matchedEmp = Employee::where('name', $pName)
                            ->orWhere('name', 'LIKE', "%{$pName}%")
                            ->first();
                        if ($matchedEmp && $matchedEmp->signature_image) {
                            $pSigBase64 = $this->imageToBase64($matchedEmp->signature_image);
                        }
                    }

                    // 3. If participant matches the claim submitter/employee
                    if (!$pSigBase64 && $emp && !empty($pName)) {
                        if (strcasecmp($emp->name, $pName) === 0 || str_contains(strtolower($pName), strtolower($emp->name))) {
                            if ($emp->signature_image) {
                                $pSigBase64 = $this->imageToBase64($emp->signature_image);
                            }
                        }
                    }

                    $processedParticipants[] = [
                        'name' => $pName,
                        'position' => $pPos,
                        'status' => $pStatus,
                        'signature_base64' => $pSigBase64,
                    ];
                }

                $attendanceList[] = [
                    'purpose' => $item['attendance_purpose'] ?? ($item['purpose'] ?? 'Pertemuan / Jamuan Makan'),
                    'place' => $item['attendance_place'] ?? ($item['note'] ?? '-'),
                    'date' => !empty($item['attendance_date']) ? date('d/m/Y', strtotime($item['attendance_date'])) : (!empty($item['receipt_date']) ? date('d/m/Y', strtotime($item['receipt_date'])) : '-'),
                    'notes' => $item['attendance_notes'] ?? null,
                    'participants' => $processedParticipants,
                ];
            }

            if (!empty($item['attendance_photos']) && is_array($item['attendance_photos'])) {
                foreach ($item['attendance_photos'] as $ap) {
                    $b64 = $this->imageToBase64($ap);
                    if ($b64) {
                        $attendanceDocsBase64[] = $b64;
                    }
                }
            }
        }

        // Budget statistics
        $entBudget = (float)($emp?->entertain_budget ?? 0);
        $entUsed = (float)($emp ? $emp->getUsedEntertainForPeriod($claim->claim_date?->month, $claim->claim_date?->year, true) : 0);
        $entRemaining = $entBudget - $entUsed;

        $pdf = Pdf::loadView('exports.single-transport-entertain-claim-pdf', [
            'claim' => $claim,
            'employee' => $emp,
            'lineItems' => $lineItems,
            'photosBase64' => $photosBase64,
            'signatureBase64' => $signatureBase64,
            'signatureSupervisorBase64' => $signatureSupervisorBase64,
            'atasanTitle' => $atasanTitle,
            'atasanName' => $atasanName,
            'atasanPosition' => $atasanPosition,
            'isApprovedByAtasan' => $isApprovedByAtasan,
            'logoBase64' => $logoBase64,
            'attendanceList' => $attendanceList,
            'attendanceDocsBase64' => $attendanceDocsBase64,
            'entBudget' => $entBudget,
            'entUsed' => $entUsed,
            'entRemaining' => $entRemaining,
        ])->setPaper('a4', 'portrait');

        $pdfContent = $pdf->output();

        return response()->streamDownload(function () use ($pdfContent) {
            echo $pdfContent;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Export Single BBM Claim to PDF (Portrait A4)
     */
    public function exportBbmSinglePdf(Claim $claim): StreamedResponse
    {
        $emp = $claim->effective_employee;
        $empName = $emp?->name ?? 'Karyawan';
        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName);
        $uid = $claim->_uid ?: 'DRAFT';
        $filename = "Nota_Balik_BBM_{$cleanName}_{$uid}_" . date('Ymd_His') . ".pdf";

        // Signature
        $signatureBase64 = null;
        if ($emp && $emp->signature_image) {
            $signatureBase64 = $this->imageToBase64($emp->signature_image);
        }

        $logoBase64 = $this->getCompanyLogoBase64();

        // BBM Pair Combined Photos
        $bbmPairs = $claim->getBbmPairCombinedPhotos();
        $pairsWithBase64 = [];
        foreach ($bbmPairs as $pair) {
            $combinedB64 = $this->imageToBase64($pair['combined_path'] ?? ($pair['combined_rel'] ?? null));
            $beforeB64 = $this->imageToBase64($pair['before_path'] ?? ($pair['before_rel'] ?? null));
            $afterB64 = $this->imageToBase64($pair['after_path'] ?? ($pair['after_rel'] ?? null));

            // Fallback to claim level if missing
            if (!$beforeB64 && !empty($claim->bbm_photo_before)) {
                $beforeB64 = $this->imageToBase64($claim->bbm_photo_before);
            }
            if (!$afterB64 && !empty($claim->bbm_photo_after)) {
                $afterB64 = $this->imageToBase64($claim->bbm_photo_after);
            }
            if (!$combinedB64 && !empty($claim->bbm_photo_combined)) {
                $combinedB64 = $this->imageToBase64($claim->bbm_photo_combined);
            }

            if ($beforeB64 || $afterB64 || $combinedB64) {
                $pairsWithBase64[] = [
                    'label' => $pair['label'] ?? 'Transaksi Pengisian BBM',
                    'spbu' => $pair['spbu'] ?? '-',
                    'date' => $pair['date'] ?? '-',
                    'amount' => $pair['amount'] ?? 0,
                    'km' => $pair['km'] ?? null,
                    'combined_b64' => $combinedB64,
                    'before_b64' => $beforeB64,
                    'after_b64' => $afterB64,
                ];
            }
        }

        // If no pairs were found from getBbmPairCombinedPhotos, check items directly
        if (empty($pairsWithBase64)) {
            if (is_array($claim->items) && count($claim->items) > 0) {
                foreach ($claim->items as $idx => $it) {
                    $before = $it['bbm_photo_before'] ?? null;
                    $after = $it['bbm_photo_after'] ?? null;
                    $combined = $it['bbm_photo_combined'] ?? null;

                    $beforeB64 = $this->imageToBase64($before);
                    $afterB64 = $this->imageToBase64($after);
                    $combinedB64 = $this->imageToBase64($combined);

                    if (!$beforeB64 && $idx === 0 && !empty($claim->bbm_photo_before)) {
                        $beforeB64 = $this->imageToBase64($claim->bbm_photo_before);
                    }
                    if (!$afterB64 && $idx === 0 && !empty($claim->bbm_photo_after)) {
                        $afterB64 = $this->imageToBase64($claim->bbm_photo_after);
                    }
                    if (!$combinedB64 && $idx === 0 && !empty($claim->bbm_photo_combined)) {
                        $combinedB64 = $this->imageToBase64($claim->bbm_photo_combined);
                    }

                    if ($beforeB64 || $afterB64 || $combinedB64) {
                        $spbuLabel = !empty($it['note']) ? $it['note'] : 'Pengisian #' . ($idx + 1);
                        $tglLabel = !empty($it['receipt_date']) ? date('d/m/Y', strtotime($it['receipt_date'])) : '-';
                        $pairsWithBase64[] = [
                            'label' => 'TRANSAKSI #' . ($idx + 1) . ' — ' . strtoupper($spbuLabel) . ' (' . $tglLabel . ')',
                            'spbu' => $spbuLabel,
                            'date' => $tglLabel,
                            'amount' => (float)($it['amount'] ?? 0),
                            'km' => $it['fuel_start_km'] ?? null,
                            'combined_b64' => $combinedB64,
                            'before_b64' => $beforeB64,
                            'after_b64' => $afterB64,
                        ];
                    }
                }
            }
        }

        // Final fallback: claim root fields
        if (empty($pairsWithBase64) && ($claim->bbm_photo_before || $claim->bbm_photo_after || $claim->bbm_photo_combined || !empty($claim->photos))) {
            $beforeB64 = $this->imageToBase64($claim->bbm_photo_before);
            $afterB64 = $this->imageToBase64($claim->bbm_photo_after);
            $combinedB64 = $this->imageToBase64($claim->bbm_photo_combined);

            if (!$beforeB64 && is_array($claim->photos) && isset($claim->photos[0])) {
                $beforeB64 = $this->imageToBase64($claim->photos[0]);
            }
            if (!$afterB64 && is_array($claim->photos) && isset($claim->photos[1])) {
                $afterB64 = $this->imageToBase64($claim->photos[1]);
            }

            if ($beforeB64 || $afterB64 || $combinedB64) {
                $pairsWithBase64[] = [
                    'label' => 'TRANSAKSI #1 — ' . strtoupper($claim->note ?: 'PENGISIAN BBM') . ' (' . ($claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-') . ')',
                    'spbu' => $claim->note ?: 'Pengisian BBM',
                    'date' => $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-',
                    'amount' => (float)$claim->amount,
                    'km' => $claim->fuel_start_km,
                    'combined_b64' => $combinedB64,
                    'before_b64' => $beforeB64,
                    'after_b64' => $afterB64,
                ];
            }
        }

        $claimPhotoBeforeBase64 = $this->imageToBase64($claim->bbm_photo_before);
        $claimPhotoAfterBase64 = $this->imageToBase64($claim->bbm_photo_after);

        // Additional general photos
        $generalPhotosBase64 = [];
        if (is_array($claim->photos)) {
            foreach ($claim->photos as $p) {
                $b64 = $this->imageToBase64($p);
                if ($b64) {
                    $generalPhotosBase64[] = $b64;
                }
            }
        }

        // Budget statistics
        $bbmBudget = (float)($emp?->bbm_budget ?? 0);
        $bbmUsed = (float)($emp ? $emp->getUsedBbmForPeriod($claim->claim_date?->month, $claim->claim_date?->year) : 0);
        $bbmRemaining = $bbmBudget - $bbmUsed;

        // Bukti Transfer Balik Sisa Saldo (ASM & RGM)
        $returnProofBase64 = null;
        if (!empty($claim->return_transfer_proof)) {
            $returnProofBase64 = $this->imageToBase64($claim->return_transfer_proof);
        }

        $pdf = Pdf::loadView('exports.single-bbm-claim-pdf', [
            'claim' => $claim,
            'employee' => $emp,
            'pairs' => $pairsWithBase64,
            'claimPhotoBeforeBase64' => $claimPhotoBeforeBase64,
            'claimPhotoAfterBase64' => $claimPhotoAfterBase64,
            'generalPhotosBase64' => $generalPhotosBase64,
            'returnProofBase64' => $returnProofBase64,
            'signatureBase64' => $signatureBase64,
            'logoBase64' => $logoBase64,
            'bbmBudget' => $bbmBudget,
            'bbmUsed' => $bbmUsed,
            'bbmRemaining' => $bbmRemaining,
        ])->setPaper('a4', 'portrait');

        $pdfContent = $pdf->output();

        return response()->streamDownload(function () use ($pdfContent) {
            echo $pdfContent;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Export Recap of Transport & Entertain Claims to PDF (Landscape A4)
     */
    public function exportTransportEntertainRecapPdf(iterable $claims, ?string $periodLabel = null): StreamedResponse
    {
        $claimsCollection = $claims instanceof Collection ? $claims : collect($claims);
        $logoBase64 = $this->getCompanyLogoBase64();

        $totalAmount = 0;
        foreach ($claimsCollection as $c) {
            $totalAmount += (float)$c->amount;
        }

        $filename = "Rekap_Klaim_Transport_Entertain_" . date('Ymd_His') . ".pdf";

        $pdf = Pdf::loadView('exports.transport-entertain-recap-pdf', [
            'claims' => $claimsCollection,
            'totalAmount' => $totalAmount,
            'periodLabel' => $periodLabel ?: 'Periode: ' . date('F Y'),
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'landscape');

        $pdfContent = $pdf->output();

        return response()->streamDownload(function () use ($pdfContent) {
            echo $pdfContent;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Export Recap of BBM Claims to PDF (Landscape A4)
     */
    public function exportBbmRecapPdf(iterable $claims, ?string $periodLabel = null): StreamedResponse
    {
        $claimsCollection = $claims instanceof Collection ? $claims : collect($claims);
        $logoBase64 = $this->getCompanyLogoBase64();

        $totalAmount = 0;
        $totalLiters = 0;
        foreach ($claimsCollection as $c) {
            $totalAmount += (float)$c->amount;
            $totalLiters += (float)($c->fuel_liters ?? 0);
        }

        $filename = "Rekap_Klaim_BBM_" . date('Ymd_His') . ".pdf";

        $pdf = Pdf::loadView('exports.bbm-recap-pdf', [
            'claims' => $claimsCollection,
            'totalAmount' => $totalAmount,
            'totalLiters' => $totalLiters,
            'periodLabel' => $periodLabel ?: 'Periode: ' . date('F Y'),
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'landscape');

        $pdfContent = $pdf->output();

        return response()->streamDownload(function () use ($pdfContent) {
            echo $pdfContent;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Export General Recap of All/Mixed Claims to PDF (Landscape A4)
     */
    public function exportGeneralClaimsRecapPdf(iterable $claims, ?string $periodLabel = null): StreamedResponse
    {
        $claimsCollection = $claims instanceof Collection ? $claims : collect($claims);
        $logoBase64 = $this->getCompanyLogoBase64();

        $totalAmount = 0;
        foreach ($claimsCollection as $c) {
            $totalAmount += (float)$c->amount;
        }

        $filename = "Rekap_Klaim_Operasional_" . date('Ymd_His') . ".pdf";

        $pdf = Pdf::loadView('exports.claims-recap-pdf', [
            'claims' => $claimsCollection,
            'totalAmount' => $totalAmount,
            'periodLabel' => $periodLabel ?: 'Periode: ' . date('F Y'),
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'landscape');

        $pdfContent = $pdf->output();

        return response()->streamDownload(function () use ($pdfContent) {
            echo $pdfContent;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Smart dispatcher for filtered claims table export
     */
    public function exportFilteredClaimsPdf(iterable $claims, ?string $periodLabel = null): StreamedResponse
    {
        $claimsCollection = $claims instanceof Collection ? $claims : collect($claims);

        if ($claimsCollection->isEmpty()) {
            return $this->exportGeneralClaimsRecapPdf($claimsCollection, $periodLabel);
        }

        $allBbm = $claimsCollection->every(function ($c) {
            return $c->claim_category === 'bbm' || in_array('BBM', (array)$c->claim_type) || str_contains(strtoupper($c->claim_type_string ?? ''), 'BBM');
        });

        if ($allBbm) {
            return $this->exportBbmRecapPdf($claimsCollection, $periodLabel);
        }

        $allTE = $claimsCollection->every(function ($c) {
            return $c->claim_category === 'transport_entertain' || str_contains(strtoupper($c->claim_type_string ?? ''), 'ENTERTAIN') || str_contains(strtoupper($c->claim_type_string ?? ''), 'TRANSPORT');
        });

        if ($allTE) {
            return $this->exportTransportEntertainRecapPdf($claimsCollection, $periodLabel);
        }

        return $this->exportGeneralClaimsRecapPdf($claimsCollection, $periodLabel);
    }

    /**
     * Export Single Perjalanan Dinas Claim to PDF Voucher with Dual Signatures (Item 6)
     */
    public function exportPerdinSinglePdf(Claim $claim): StreamedResponse
    {
        $emp = $claim->effective_employee;
        $empName = $emp?->name ?? 'Karyawan';
        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName);
        $uid = $claim->_uid ?: 'DRAFT';
        $filename = "Klaim_Perdin_{$cleanName}_{$uid}_" . date('Ymd_His') . ".pdf";

        // TTD Pemohon
        $signatureApplicantBase64 = null;
        if ($emp && $emp->signature_image) {
            $signatureApplicantBase64 = $this->imageToBase64($emp->signature_image);
        }

        // TTD Atasan (Item 6): Sales & ASM -> RGM; RGM -> Head of Sales (Pak Jejen)
        $roleName = strtoupper($emp?->role?->code ?? $emp?->role?->name ?? $emp?->position ?? '');
        $isRgm = str_contains($roleName, 'RGM') || ($claim->user && $claim->user->isRgm());

        $signatureSupervisorBase64 = null;
        if ($isRgm) {
            // Atasan RGM adalah Head of Sales (Pak Jejen)
            $atasanTitle = 'Disetujui Head of Sales';
            $atasanName = $claim->approvedByJejen?->name ?? 'Pak Jejen';
            $atasanPosition = 'Head of Sales';
            $isApprovedByAtasan = !empty($claim->approved_by_jejen_at) || !empty($claim->approved_by_jejen_id);
            if ($claim->approvedByJejen?->employee?->signature_image) {
                $signatureSupervisorBase64 = $this->imageToBase64($claim->approvedByJejen->employee->signature_image);
            }
        } else {
            // Atasan Sales dan ASM adalah RGM
            $atasanTitle = 'Disetujui Atasan (RGM)';
            $atasanUser = $claim->approvedByRgm ?? $claim->approvedByAsm;
            $atasanName = $atasanUser?->name ?? 'RGM Wilayah';
            $atasanPosition = 'Regional General Manager';
            $isApprovedByAtasan = !empty($claim->approved_by_rgm_at) || !empty($claim->approved_by_asm_at) || !empty($claim->approved_by_rgm_id);
            if ($atasanUser?->employee?->signature_image) {
                $signatureSupervisorBase64 = $this->imageToBase64($atasanUser->employee->signature_image);
            }
        }

        $logoBase64 = $this->getCompanyLogoBase64();

        $pdf = Pdf::loadView('exports.single-perdin-claim-pdf', [
            'claim' => $claim,
            'employee' => $emp,
            'logoBase64' => $logoBase64,
            'signatureApplicantBase64' => $signatureApplicantBase64,
            'signatureSupervisorBase64' => $signatureSupervisorBase64,
            'atasanTitle' => $atasanTitle,
            'atasanName' => $atasanName,
            'atasanPosition' => $atasanPosition,
            'isApprovedByAtasan' => $isApprovedByAtasan,
            'financeName' => $claim->financeApprovedBy?->name ?? 'Tim Finance',
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Export Single Vehicle Service Claim with Before & After Photos (Item 2)
     */
    public function exportVehicleServiceSinglePdf(Claim $claim): StreamedResponse
    {
        $emp = $claim->effective_employee;
        $empName = $emp?->name ?? 'Karyawan';
        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName);
        $uid = $claim->_uid ?: 'DRAFT';
        $filename = "Klaim_Service_Motor_{$cleanName}_{$uid}_" . date('Ymd_His') . ".pdf";

        // TTD Pemohon
        $signatureApplicantBase64 = null;
        if ($emp && $emp->signature_image) {
            $signatureApplicantBase64 = $this->imageToBase64($emp->signature_image);
        }

        // TTD Atasan
        $signatureSupervisorBase64 = null;
        $supervisor = $claim->approvedByRgm ?? $claim->approvedByAsm;
        if ($supervisor?->employee?->signature_image) {
            $signatureSupervisorBase64 = $this->imageToBase64($supervisor->employee->signature_image);
        }

        // TTD Jejen
        $signatureJejenBase64 = null;
        if ($claim->approvedByJejen?->employee?->signature_image) {
            $signatureJejenBase64 = $this->imageToBase64($claim->approvedByJejen->employee->signature_image);
        }

        // FOTO BEFORE & AFTER (Item 2)
        $photoBeforeBase64 = $this->imageToBase64($claim->service_photo_before);
        $photoAfterBase64 = $this->imageToBase64($claim->service_photo_after);
        $photoCombinedBase64 = $this->imageToBase64($claim->service_photo_combined);

        // Fallbacks if service_photo_before / after are empty but photos array has items
        if (!$photoBeforeBase64 && is_array($claim->photos) && isset($claim->photos[0])) {
            $photoBeforeBase64 = $this->imageToBase64($claim->photos[0]);
        }
        if (!$photoAfterBase64 && is_array($claim->photos) && isset($claim->photos[1])) {
            $photoAfterBase64 = $this->imageToBase64($claim->photos[1]);
        }

        // Check items fallback if claim->items has before/after
        if ((!$photoBeforeBase64 || !$photoAfterBase64) && is_array($claim->items) && count($claim->items) > 0) {
            foreach ($claim->items as $it) {
                if (!$photoBeforeBase64 && !empty($it['service_photo_before'])) {
                    $photoBeforeBase64 = $this->imageToBase64($it['service_photo_before']);
                }
                if (!$photoAfterBase64 && !empty($it['service_photo_after'])) {
                    $photoAfterBase64 = $this->imageToBase64($it['service_photo_after']);
                }
                if (!$photoCombinedBase64 && !empty($it['service_photo_combined'])) {
                    $photoCombinedBase64 = $this->imageToBase64($it['service_photo_combined']);
                }
            }
        }

        // Receipts
        $receiptPhotosBase64 = [];
        if (is_array($claim->photos)) {
            foreach ($claim->photos as $p) {
                $b64 = $this->imageToBase64($p);
                if ($b64) $receiptPhotosBase64[] = $b64;
            }
        }

        $logoBase64 = $this->getCompanyLogoBase64();

        $pdf = Pdf::loadView('exports.single-service-claim-pdf', [
            'claim' => $claim,
            'employee' => $emp,
            'logoBase64' => $logoBase64,
            'photoBeforeBase64' => $photoBeforeBase64,
            'photoAfterBase64' => $photoAfterBase64,
            'photoCombinedBase64' => $photoCombinedBase64,
            'receiptPhotosBase64' => $receiptPhotosBase64,
            'signatureApplicantBase64' => $signatureApplicantBase64,
            'signatureSupervisorBase64' => $signatureSupervisorBase64,
            'signatureJejenBase64' => $signatureJejenBase64,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Export Single Nota Balik Perdin to PDF (Item 15)
     */
    public function exportNotaBalikSinglePdf(Claim $claim): StreamedResponse
    {
        $emp = $claim->effective_employee;
        $empName = $emp?->name ?? 'Karyawan';
        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName);
        $uid = $claim->_uid ?: 'DRAFT';
        $filename = "Nota_Balik_Perdin_{$cleanName}_{$uid}_" . date('Ymd_His') . ".pdf";

        // TTD Pemohon
        $signatureApplicantBase64 = null;
        if ($emp && $emp->signature_image) {
            $signatureApplicantBase64 = $this->imageToBase64($emp->signature_image);
        }

        $returnProofBase64 = $this->imageToBase64($claim->return_transfer_proof);

        $receiptPhotosBase64 = [];
        if (is_array($claim->photos)) {
            foreach ($claim->photos as $p) {
                $b64 = $this->imageToBase64($p);
                if ($b64) $receiptPhotosBase64[] = $b64;
            }
        }

        $logoBase64 = $this->getCompanyLogoBase64();

        $pdf = Pdf::loadView('exports.single-nota-balik-pdf', [
            'claim' => $claim,
            'employee' => $emp,
            'logoBase64' => $logoBase64,
            'signatureApplicantBase64' => $signatureApplicantBase64,
            'returnProofBase64' => $returnProofBase64,
            'receiptPhotosBase64' => $receiptPhotosBase64,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Generate a digital handwritten-style paraf/signature PNG base64 for participant
     */
    public function generateDigitalParafBase64(string $name): string
    {
        $width = 160;
        $height = 65;
        $img = imagecreatetruecolor($width, $height);

        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefilledrectangle($img, 0, 0, $width, $height, $transparent);
        imagealphablending($img, true);

        $ink = imagecolorallocate($img, 15, 23, 42);
        $blueInk = imagecolorallocate($img, 2, 132, 199);
        $lineColor = imagecolorallocate($img, 148, 163, 184);

        $seed = crc32($name);
        mt_srand($seed);

        imagesetthickness($img, 2);
        $startX = 20;
        $startY = 32 + mt_rand(-4, 4);
        $points = [];
        $points[] = [$startX, $startY];
        
        $p1X = $startX + mt_rand(15, 22);
        $p1Y = $startY - mt_rand(12, 20);
        $points[] = [$p1X, $p1Y];

        $p2X = $p1X + mt_rand(15, 22);
        $p2Y = $startY + mt_rand(6, 12);
        $points[] = [$p2X, $p2Y];

        $p3X = $p2X + mt_rand(20, 30);
        $p3Y = $startY - mt_rand(8, 15);
        $points[] = [$p3X, $p3Y];

        $endX = min($p3X + mt_rand(25, 35), $width - 15);
        $endY = $startY + mt_rand(-3, 6);
        $points[] = [$endX, $endY];

        for ($i = 0; $i < count($points) - 1; $i++) {
            imageline($img, $points[$i][0], $points[$i][1], $points[$i+1][0], $points[$i+1][1], $ink);
        }

        imagearc($img, $startX + 10, $startY - 6, 20, 24, 0, 360, $ink);
        imageline($img, $startX - 5, $height - 18, $endX + 5, $height - 18, $lineColor);
        imagestring($img, 1, $startX, $height - 14, Str::limit(strtoupper($name), 16), $blueInk);

        ob_start();
        imagepng($img);
        $pngData = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($pngData);
    }
}

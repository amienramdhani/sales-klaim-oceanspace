<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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

        // 1. Check storage/public disk
        if (Storage::disk('public')->exists($path)) {
            try {
                $contents = Storage::disk('public')->get($path);
                $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            } catch (\Throwable $e) {
                // Ignore and try local filesystem
            }
        }

        // 2. Check local filesystem path
        if (file_exists($path) && is_file($path)) {
            try {
                $contents = file_get_contents($path);
                $mime = mime_content_type($path) ?: 'image/jpeg';
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            } catch (\Throwable $e) {
                return null;
            }
        }

        // 3. Check public_path
        $publicPath = public_path($path);
        if (file_exists($publicPath) && is_file($publicPath)) {
            try {
                $contents = file_get_contents($publicPath);
                $mime = mime_content_type($publicPath) ?: 'image/jpeg';
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            } catch (\Throwable $e) {
                return null;
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
     * Export a single claim to PDF (auto-detects BBM, Transport & Entertain, or other)
     */
    public function exportSingleClaimPdf(Claim $claim): StreamedResponse
    {
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

        // Signature
        $signatureBase64 = null;
        if ($emp && $emp->signature_image) {
            $signatureBase64 = $this->imageToBase64($emp->signature_image);
        }

        $logoBase64 = $this->getCompanyLogoBase64();
        $lineItems = $claim->getLineItems();

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
            'logoBase64' => $logoBase64,
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
        $filename = "Klaim_BBM_{$cleanName}_{$uid}_" . date('Ymd_His') . ".pdf";

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
            $combinedB64 = !empty($pair['combined_path']) ? $this->imageToBase64($pair['combined_path']) : null;
            $beforeB64 = !empty($pair['before_path']) ? $this->imageToBase64($pair['before_path']) : null;
            $afterB64 = !empty($pair['after_path']) ? $this->imageToBase64($pair['after_path']) : null;

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

        $pdf = Pdf::loadView('exports.single-bbm-claim-pdf', [
            'claim' => $claim,
            'employee' => $emp,
            'pairs' => $pairsWithBase64,
            'generalPhotosBase64' => $generalPhotosBase64,
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
}

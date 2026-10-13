<?php

namespace App\Filament\Resources\BbmClaimResource\Pages;

use App\Filament\Resources\BbmClaimResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBbmClaim extends CreateRecord
{
    protected static string $resource = BbmClaimResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['user_id'] = $data['user_id'] ?? $user?->id;
        $data['employee_id'] = $data['employee_id'] ?? $user?->getEffectiveEmployeeId();
        $data['claim_category'] = 'bbm';
        $data['claim_type'] = 'Nota Balik BBM';
        $emp = !empty($data['employee_id']) ? \App\Models\Employee::find($data['employee_id']) : null;
        $isAsm = $user?->isAsm() || $emp?->isAsm();
        $isRgm = $user?->isRgm() || $emp?->isRgm();

        if ($isAsm) {
            $data['approval_status'] = 'ACC_ASM';
            $data['approved_by_asm_id'] = $user?->id;
            $data['approved_by_asm_at'] = now();
        } elseif ($isRgm) {
            $data['approval_status'] = 'ACC_RGM';
            $data['approved_by_rgm_id'] = $user?->id;
            $data['approved_by_rgm_at'] = now();
        } else {
            $data['approval_status'] = $data['approval_status'] ?? 'DIAJUKAN';
        }

        $data['disbursement_status'] = $data['disbursement_status'] ?? 'Belum Dicairkan';
        if ($user && !$user->isAdmin() && !$user->isSuperAdmin()) {
            $data['region'] = $user->getOperationalRegion();
            $data['homebase'] = $data['homebase'] ?? $user->homebase ?? $user->employee?->homebase;
        }

        if (!empty($data['items']) && is_array($data['items'])) {
            $firstItem = reset($data['items']);
            $data['fuel_start_km'] = $data['fuel_start_km'] ?? ($firstItem['fuel_start_km'] ?? null);
            $data['fuel_type'] = $data['fuel_type'] ?? ($firstItem['fuel_type'] ?? 'Pertalite');
            $data['bbm_photo_before'] = $data['bbm_photo_before'] ?? ($firstItem['bbm_photo_before'] ?? null);
            $data['bbm_photo_after'] = $data['bbm_photo_after'] ?? ($firstItem['bbm_photo_after'] ?? null);
            $totalAmount = array_sum(array_column($data['items'], 'amount'));
            $totalBase = array_sum(array_column($data['items'], 'fuel_base_amount'));
            $data['amount'] = $totalAmount ?: ($data['amount'] ?? 0);
            $data['fuel_base_amount'] = $totalBase ?: ($data['fuel_base_amount'] ?? $data['amount']);
        } elseif (empty($data['items'])) {
            $photos = $data['photos'] ?? [];
            $photoBefore = is_array($photos) ? ($photos[0] ?? null) : null;
            $photoAfter = is_array($photos) && count($photos) > 1 ? $photos[1] : null;

            $data['bbm_photo_before'] = $data['bbm_photo_before'] ?? $photoBefore;
            $data['bbm_photo_after'] = $data['bbm_photo_after'] ?? $photoAfter;
            $data['fuel_base_amount'] = $data['fuel_base_amount'] ?? ($data['amount'] ?? 0);

            $data['items'] = [
                [
                    'claim_type' => 'BBM',
                    'receipt_date' => $data['claim_date'] ?? now()->toDateString(),
                    'fuel_type' => $data['fuel_type'] ?? 'Pertalite',
                    'fuel_start_km' => $data['fuel_start_km'] ?? null,
                    'fuel_base_amount' => $data['amount'] ?? 0,
                    'amount' => $data['amount'] ?? 0,
                    'bbm_photo_before' => $data['bbm_photo_before'],
                    'bbm_photo_after' => $data['bbm_photo_after'],
                ]
            ];
        }

        // Validasi Over Budget BBM: Wajib lampirkan Berita Acara (BA)
        $emp = \App\Models\Employee::find($data['employee_id'] ?? null);
        $budget = $emp ? (float)$emp->bbm_budget : 0.0;
        $totalAmount = (float)($data['amount'] ?? 0);

        if ($budget > 0 && $totalAmount > $budget) {
            // Bersihkan sisa transfer balik karena over budget
            $data['return_transfer_amount'] = null;
            $data['return_transfer_proof'] = null;
            $data['return_transfer_notes'] = null;

            if (empty($data['ba_wa_proof_photo'])) {
                \Filament\Notifications\Notification::make()
                    ->title('Wajib Melampirkan Berita Acara (BA)')
                    ->body('Pengeluaran BBM sebesar Rp ' . number_format($totalAmount, 0, ',', '.') . ' melebihi budget plafon bulanan (Rp ' . number_format($budget, 0, ',', '.') . '). Anda wajib membuat Berita Acara (BA) dan mengunggah bukti persetujuannya.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

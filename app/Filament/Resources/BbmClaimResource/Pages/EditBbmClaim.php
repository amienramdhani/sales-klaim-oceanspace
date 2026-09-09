<?php

namespace App\Filament\Resources\BbmClaimResource\Pages;

use App\Filament\Resources\BbmClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBbmClaim extends EditRecord
{
    protected static string $resource = BbmClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (empty($data['fuel_start_km']) && !empty($data['items'][0]['fuel_start_km'])) {
            $data['fuel_start_km'] = $data['items'][0]['fuel_start_km'];
        }
        if (empty($data['fuel_type']) && !empty($data['items'][0]['fuel_type'])) {
            $data['fuel_type'] = $data['items'][0]['fuel_type'];
        }
        if (empty($data['photos'])) {
            $photos = [];
            if (!empty($data['bbm_photo_before'])) $photos[] = $data['bbm_photo_before'];
            if (!empty($data['bbm_photo_after'])) $photos[] = $data['bbm_photo_after'];
            if (!empty($photos)) $data['photos'] = $photos;
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        if ($user && $user->isFieldUser() && !$user->isAdmin() && !$user->isSuperAdmin()) {
            $data['fuel_base_amount'] = $data['amount'] ?? 0;
            $photos = $data['photos'] ?? [];
            $photoBefore = is_array($photos) ? ($photos[0] ?? null) : null;
            $photoAfter = is_array($photos) && count($photos) > 1 ? $photos[1] : null;

            if (!empty($photoBefore)) {
                $data['bbm_photo_before'] = $photoBefore;
            }
            if (!empty($photoAfter)) {
                $data['bbm_photo_after'] = $photoAfter;
            }

            $data['items'] = [
                [
                    'claim_type' => 'BBM',
                    'receipt_date' => $data['claim_date'] ?? now()->toDateString(),
                    'fuel_type' => $data['fuel_type'] ?? 'Pertalite',
                    'fuel_start_km' => $data['fuel_start_km'] ?? null,
                    'fuel_base_amount' => $data['amount'] ?? 0,
                    'amount' => $data['amount'] ?? 0,
                    'bbm_photo_before' => $data['bbm_photo_before'] ?? $photoBefore,
                    'bbm_photo_after' => $data['bbm_photo_after'] ?? $photoAfter,
                ]
            ];
        }

        if ($this->record && $this->record->approval_status === 'DITOLAK') {
            $data['approval_status'] = 'SEDANG_DIREVISI';
        }

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        if ($this->record && in_array($this->record->approval_status, ['DITOLAK', 'SEDANG_DIREVISI'])) {
            return 'Data klaim berhasil diperbarui dan status menjadi Sedang Direvisi. Silakan klik Ajukan Kembali!';
        }

        return 'Perubahan berhasil disimpan!';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

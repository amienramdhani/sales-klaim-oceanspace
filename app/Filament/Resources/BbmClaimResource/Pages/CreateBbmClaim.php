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
        if ($user && $user->isFieldUser() && !$user->isAdmin() && !$user->isSuperAdmin()) {
            $data['user_id'] = $user->id;
            $data['employee_id'] = $data['employee_id'] ?? $user->getEffectiveEmployeeId();
            $data['claim_category'] = 'bbm';
            $data['claim_type'] = 'Klaim BBM';
            $data['approval_status'] = 'DIAJUKAN';
            $data['region'] = $user->getOperationalRegion();
            $data['homebase'] = $data['homebase'] ?? $user->homebase ?? $user->employee?->homebase;
            $data['fuel_base_amount'] = $data['amount'] ?? 0;

            $photos = $data['photos'] ?? [];
            $photoBefore = is_array($photos) ? ($photos[0] ?? null) : null;
            $photoAfter = is_array($photos) && count($photos) > 1 ? $photos[1] : null;

            $data['bbm_photo_before'] = $data['bbm_photo_before'] ?? $photoBefore;
            $data['bbm_photo_after'] = $data['bbm_photo_after'] ?? $photoAfter;

            if (empty($data['items'])) {
                $data['items'] = [
                    [
                        'claim_type' => 'BBM',
                        'receipt_date' => $data['claim_date'] ?? now()->toDateString(),
                        'fuel_type' => $data['fuel_type'] ?? 'Pertalite',
                        'fuel_start_km' => $data['fuel_start_km'] ?? null,
                        'fuel_base_amount' => $data['amount'] ?? 0,
                        'amount' => $data['amount'] ?? 0,
                        'bbm_photo_before' => $photoBefore,
                        'bbm_photo_after' => $photoAfter,
                    ]
                ];
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

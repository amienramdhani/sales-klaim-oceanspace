<?php

namespace App\Filament\Resources\VehicleServiceClaimResource\Pages;

use App\Filament\Resources\VehicleServiceClaimResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleServiceClaim extends CreateRecord
{
    protected static string $resource = VehicleServiceClaimResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user) {
            $data['user_id'] = $data['user_id'] ?? $user->id;
            $data['employee_id'] = $data['employee_id'] ?? $user->getEffectiveEmployeeId();
            $data['claim_category'] = 'service_motor';
            if (!$user->isAdmin() && !$user->isSuperAdmin()) {
                $data['approval_status'] = 'DIAJUKAN';
            }
            $data['region'] = $user->getOperationalRegion();
            $data['homebase'] = $data['homebase'] ?? $user->homebase ?? $user->employee?->homebase;
        }
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

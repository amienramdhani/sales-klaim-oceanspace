<?php

namespace App\Filament\Resources\PerdinClaimResource\Pages;

use App\Filament\Resources\PerdinClaimResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerdinClaim extends CreateRecord
{
    protected static string $resource = PerdinClaimResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user) {
            $data['user_id'] = $data['user_id'] ?? $user->id;
            $data['employee_id'] = $data['employee_id'] ?? $user->getEffectiveEmployeeId();
            $data['claim_category'] = 'perdin';
            $data['is_perdin'] = true;
            if (!$user->isAdmin() && !$user->isSuperAdmin()) {
                $data['approval_status'] = 'DIAJUKAN';
            }
            $data['region'] = $user->getOperationalRegion();
            $data['homebase'] = $data['homebase'] ?? $user->homebase ?? $user->employee?->homebase;
        }

        $data['meal_allowance'] = (float)($data['meal_allowance'] ?? 0);
        $data['lodging_allowance'] = (float)($data['lodging_allowance'] ?? 0);
        $data['toll_cost'] = (float)($data['toll_cost'] ?? 0);
        $data['fuel_cost'] = (float)($data['fuel_cost'] ?? 0);
        $data['car_rental_cost'] = (float)($data['car_rental_cost'] ?? 0);
        $data['service_cost'] = (float)($data['service_cost'] ?? 0);
        $data['days_count'] = max(1, (int)($data['days_count'] ?? 1));
        $data['nights_count'] = max(0, (int)($data['nights_count'] ?? 0));
        $data['amount'] = (float)($data['amount'] ?? 0);
        if ($data['amount'] <= 0) {
            $data['amount'] = $data['meal_allowance'] + $data['lodging_allowance'] + $data['toll_cost'] + $data['fuel_cost'] + $data['car_rental_cost'] + $data['service_cost'];
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

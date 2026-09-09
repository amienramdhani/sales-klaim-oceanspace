<?php

namespace App\Filament\Resources\PerdinClaimResource\Pages;

use App\Filament\Resources\PerdinClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPerdinClaim extends EditRecord
{
    protected static string $resource = PerdinClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->approval_status === 'DITOLAK') {
            $data['approval_status'] = 'SEDANG_DIREVISI';
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

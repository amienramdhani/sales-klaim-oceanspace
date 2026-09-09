<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['bbm_budget'] = $data['bbm_budget'] !== null && $data['bbm_budget'] !== '' ? (float)$data['bbm_budget'] : 0;
        $data['perdin_budget'] = $data['perdin_budget'] !== null && $data['perdin_budget'] !== '' ? (float)$data['perdin_budget'] : 0;
        $data['transport_budget'] = $data['transport_budget'] !== null && $data['transport_budget'] !== '' ? (float)$data['transport_budget'] : 0;
        $data['perdin_meal_allowance'] = $data['perdin_meal_allowance'] !== null && $data['perdin_meal_allowance'] !== '' ? (float)$data['perdin_meal_allowance'] : 100000;
        $data['perdin_lodging_allowance'] = $data['perdin_lodging_allowance'] !== null && $data['perdin_lodging_allowance'] !== '' ? (float)$data['perdin_lodging_allowance'] : 250000;
        $data['perdin_transport_budget'] = $data['perdin_transport_budget'] !== null && $data['perdin_transport_budget'] !== '' ? (float)$data['perdin_transport_budget'] : 500000;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

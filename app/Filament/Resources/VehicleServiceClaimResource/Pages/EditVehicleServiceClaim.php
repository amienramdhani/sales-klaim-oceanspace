<?php

namespace App\Filament\Resources\VehicleServiceClaimResource\Pages;

use App\Filament\Resources\VehicleServiceClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVehicleServiceClaim extends EditRecord
{
    protected static string $resource = VehicleServiceClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

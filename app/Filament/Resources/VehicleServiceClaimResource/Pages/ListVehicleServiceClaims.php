<?php

namespace App\Filament\Resources\VehicleServiceClaimResource\Pages;

use App\Filament\Resources\VehicleServiceClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVehicleServiceClaims extends ListRecords
{
    protected static string $resource = VehicleServiceClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Klaim Service Kendaraan'),
        ];
    }
}

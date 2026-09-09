<?php

namespace App\Filament\Resources\FuelPriceResource\Pages;

use App\Filament\Resources\FuelPriceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFuelPrice extends CreateRecord
{
    protected static string $resource = FuelPriceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

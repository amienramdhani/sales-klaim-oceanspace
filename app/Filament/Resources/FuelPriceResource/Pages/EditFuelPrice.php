<?php

namespace App\Filament\Resources\FuelPriceResource\Pages;

use App\Filament\Resources\FuelPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFuelPrice extends EditRecord
{
    protected static string $resource = FuelPriceResource::class;

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
